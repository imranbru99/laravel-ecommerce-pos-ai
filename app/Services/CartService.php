<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class CartService
{
    /**
     * Get or create active Cart for current user/session.
     */
    public function getCart(): Cart
    {
        $userId = Auth::id();
        $sessionId = Session::getId();

        if ($userId) {
            $cart = Cart::with(['items.product.images', 'items.variant'])->firstOrCreate(
                ['user_id' => $userId],
                ['session_id' => $sessionId, 'currency' => 'USD']
            );

            // Merge guest cart if exists
            $guestCart = Cart::whereNull('user_id')->where('session_id', $sessionId)->first();
            if ($guestCart && $guestCart->id !== $cart->id) {
                foreach ($guestCart->items as $item) {
                    $this->addItem(
                        $item->product_id,
                        $item->quantity,
                        $item->product_variant_id,
                        $item->options ?? []
                    );
                }
                $guestCart->delete();
                $cart->load('items.product.images', 'items.variant');
            }

            return $cart;
        }

        return Cart::with(['items.product.images', 'items.variant'])->firstOrCreate(
            ['session_id' => $sessionId, 'user_id' => null],
            ['currency' => 'USD']
        );
    }

    /**
     * Add product/variant to cart.
     */
    public function addItem(int $productId, int $quantity = 1, ?int $variantId = null, array $options = []): CartItem
    {
        $cart = $this->getCart();
        $product = Product::findOrFail($productId);
        $variant = $variantId ? ProductVariant::findOrFail($variantId) : null;

        $unitPrice = $variant ? $variant->price : $product->price;
        $salePrice = $variant ? $variant->sale_price : $product->sale_price;

        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->first();

        if ($cartItem) {
            $cartItem->quantity += $quantity;
            $cartItem->unit_price = $unitPrice;
            $cartItem->sale_price = $salePrice;
            $cartItem->save();
        } else {
            $cartItem = CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'sale_price' => $salePrice,
                'options' => $options,
            ]);
        }

        $this->recalculateCart($cart);

        return $cartItem;
    }

    /**
     * Update quantity of an item.
     */
    public function updateQuantity(int $itemId, int $quantity): bool
    {
        $cart = $this->getCart();
        $cartItem = CartItem::where('cart_id', $cart->id)->where('id', $itemId)->first();

        if (!$cartItem) {
            return false;
        }

        if ($quantity <= 0) {
            $cartItem->delete();
        } else {
            $cartItem->quantity = $quantity;
            $cartItem->save();
        }

        $this->recalculateCart($cart);

        return true;
    }

    /**
     * Remove item from cart.
     */
    public function removeItem(int $itemId): bool
    {
        $cart = $this->getCart();
        $deleted = CartItem::where('cart_id', $cart->id)->where('id', $itemId)->delete();
        $this->recalculateCart($cart);

        return (bool) $deleted;
    }

    /**
     * Clear all items from cart.
     */
    public function clear(): void
    {
        $cart = $this->getCart();
        $cart->items()->delete();
        $cart->discount_amount = 0;
        $cart->coupon_id = null;
        $cart->save();
    }

    /**
     * Apply coupon to current cart.
     */
    public function applyCoupon(string $code): array
    {
        $cart = $this->getCart();
        $coupon = Coupon::where('code', strtoupper($code))->first();

        if (!$coupon) {
            return ['success' => false, 'message' => 'Invalid coupon code.'];
        }

        $subtotal = $cart->subtotal;
        if (!$coupon->isValidForAmount($subtotal)) {
            return ['success' => false, 'message' => 'Coupon is expired or minimum spend not met.'];
        }

        $discount = $coupon->calculateDiscount($subtotal);
        $cart->coupon_id = $coupon->id;
        $cart->discount_amount = $discount;
        $cart->save();

        return [
            'success' => true,
            'message' => 'Coupon applied successfully!',
            'discount' => $discount,
            'total' => $cart->total,
        ];
    }

    protected function recalculateCart(Cart $cart): void
    {
        $cart->load('items');
        if ($cart->coupon_id) {
            $coupon = Coupon::find($cart->coupon_id);
            if ($coupon && $coupon->isValidForAmount($cart->subtotal)) {
                $cart->discount_amount = $coupon->calculateDiscount($cart->subtotal);
            } else {
                $cart->coupon_id = null;
                $cart->discount_amount = 0;
            }
        } else {
            $cart->discount_amount = 0;
        }

        $cart->save();
    }
}
