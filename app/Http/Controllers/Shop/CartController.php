<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class CartController extends Controller
{
    protected CartService $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     * View shopping cart.
     */
    public function index(): View
    {
        $cart = $this->cartService->getCart();
        return view('shop.cart.index', compact('cart'));
    }

    /**
     * Add an item to the cart.
     */
    public function add(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'nullable|integer|min:1',
            'variant_id' => 'nullable|exists:product_variants,id',
            'options' => 'nullable|array',
        ]);

        $item = $this->cartService->addItem(
            (int) $validated['product_id'],
            (int) ($validated['quantity'] ?? 1),
            isset($validated['variant_id']) ? (int) $validated['variant_id'] : null,
            $validated['options'] ?? []
        );

        if ($request->wantsJson()) {
            $cart = $this->cartService->getCart();
            return response()->json([
                'success' => true,
                'message' => 'Product added to cart!',
                'cart_count' => $cart->total_quantity,
                'cart_total' => $cart->total,
            ]);
        }

        return redirect()->back()->with('success', 'Product added to cart!');
    }

    /**
     * Update item quantity.
     */
    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:0',
        ]);

        $this->cartService->updateQuantity($id, (int) $validated['quantity']);

        if ($request->wantsJson()) {
            $cart = $this->cartService->getCart();
            return response()->json([
                'success' => true,
                'message' => 'Cart updated!',
                'cart_total' => $cart->total,
                'cart_count' => $cart->total_quantity,
            ]);
        }

        return redirect()->back()->with('success', 'Cart updated successfully.');
    }

    /**
     * Remove an item from the cart.
     */
    public function remove(Request $request, int $id)
    {
        $this->cartService->removeItem($id);

        if ($request->wantsJson()) {
            $cart = $this->cartService->getCart();
            return response()->json([
                'success' => true,
                'message' => 'Item removed from cart.',
                'cart_total' => $cart->total,
                'cart_count' => $cart->total_quantity,
            ]);
        }

        return redirect()->back()->with('success', 'Item removed from cart.');
    }

    /**
     * Apply a discount coupon.
     */
    public function applyCoupon(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
        ]);

        $result = $this->cartService->applyCoupon($validated['code']);

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        return redirect()->back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    /**
     * Mini cart payload for navbar drawer.
     */
    public function miniCart(): JsonResponse
    {
        $cart = $this->cartService->getCart();

        return response()->json([
            'count' => $cart->total_quantity,
            'subtotal' => $cart->subtotal,
            'total' => $cart->total,
            'items' => $cart->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->product->name,
                    'quantity' => $item->quantity,
                    'price' => $item->effective_price,
                    'image' => $item->product->primaryImage?->path,
                ];
            }),
        ]);
    }
}
