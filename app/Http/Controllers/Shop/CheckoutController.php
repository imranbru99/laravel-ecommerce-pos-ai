<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Setting;
use App\Services\CartService;
use App\Services\Payment\MfsPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    protected CartService $cartService;
    protected MfsPaymentService $mfsPaymentService;

    public function __construct(CartService $cartService, MfsPaymentService $mfsPaymentService)
    {
        $this->cartService = $cartService;
        $this->mfsPaymentService = $mfsPaymentService;
    }

    /**
     * Display checkout page.
     */
    public function index(): View
    {
        $cart = $this->cartService->getCart();

        if ($cart->items->isEmpty()) {
            return redirect()->route('cart.index')->with('warning', 'Your cart is empty.');
        }

        $user = Auth::user();
        $addresses = $user ? $user->addresses : collect();
        $defaultAddress = $user ? $user->defaultShippingAddress : null;

        $shippingRate = (float) Setting::get('default_shipping_fee', 10.00);
        $taxRate = (float) Setting::get('default_tax_rate', 0.00);
        $taxAmount = ($cart->total * $taxRate) / 100;
        $grandTotal = $cart->total + $shippingRate + $taxAmount;

        return view('shop.checkout.index', compact(
            'cart',
            'user',
            'addresses',
            'defaultAddress',
            'shippingRate',
            'taxAmount',
            'grandTotal'
        ));
    }

    /**
     * Process order submission and route to payment.
     */
    public function process(Request $request)
    {
        $cart = $this->cartService->getCart();

        if ($cart->items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:80',
            'last_name' => 'required|string|max:80',
            'email' => 'required|email|max:150',
            'phone' => 'required|string|max:25',
            'address_line_1' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'country' => 'required|string|max:2',
            'payment_method' => 'required|in:cod,stripe,paypal,bkash,nagad',
            'customer_note' => 'nullable|string|max:500',
        ]);

        $shippingFee = (float) Setting::get('default_shipping_fee', 10.00);
        $taxRate = (float) Setting::get('default_tax_rate', 0.00);
        $taxAmount = ($cart->total * $taxRate) / 100;
        $total = $cart->total + $shippingFee + $taxAmount;

        $order = DB::transaction(function () use ($validated, $cart, $shippingFee, $taxAmount, $total, $request) {
            $order = Order::create([
                'user_id' => Auth::id(),
                'coupon_id' => $cart->coupon_id,
                'order_number' => Order::generateOrderNumber(),
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'currency' => $cart->currency ?? 'USD',
                'subtotal' => $cart->subtotal,
                'discount_amount' => $cart->discount_amount,
                'shipping_amount' => $shippingFee,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'coupon_code' => $cart->coupon?->code,
                'coupon_discount' => $cart->discount_amount,
                'shipping_method' => 'Standard Delivery',
                'customer_note' => $validated['customer_note'] ?? null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Save Shipping Address
            OrderAddress::create([
                'order_id' => $order->id,
                'type' => 'shipping',
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'address_line_1' => $validated['address_line_1'],
                'city' => $validated['city'],
                'postal_code' => $validated['postal_code'],
                'country' => $validated['country'],
                'phone' => $validated['phone'],
                'email' => $validated['email'],
            ]);

            // Create Order Items and update stock
            foreach ($cart->items as $cartItem) {
                $product = $cartItem->product;
                $variant = $cartItem->variant;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'product_name' => $product->name,
                    'variant_name' => $variant ? $variant->sku : null,
                    'sku' => $variant ? $variant->sku : $product->sku,
                    'product_image' => $product->primaryImage?->path,
                    'quantity' => $cartItem->quantity,
                    'unit_price' => $cartItem->unit_price,
                    'sale_price' => $cartItem->sale_price,
                    'subtotal' => $cartItem->subtotal,
                    'total' => $cartItem->subtotal,
                    'options' => $cartItem->options,
                ]);

                // Deduct stock
                if ($product->manage_stock) {
                    $product->decrement('stock', $cartItem->quantity);
                    if ($product->stock <= 0) {
                        $product->update(['stock_status' => 'out_of_stock']);
                    }
                }

                $product->increment('sales_count', $cartItem->quantity);
            }

            // Record initial timeline status
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => 'pending',
                'comment' => 'Order placed successfully by customer.',
                'changed_by' => Auth::id(),
            ]);

            // Clear Cart
            $this->cartService->clear();

            return $order;
        });

        // Route by Payment Method
        if ($validated['payment_method'] === 'bkash') {
            $bkash = $this->mfsPaymentService->initiateBkashPayment($order);
            if ($bkash['success'] && isset($bkash['redirectURL'])) {
                return redirect()->away($bkash['redirectURL']);
            }
        }

        if ($validated['payment_method'] === 'cod') {
            return redirect()->route('shop.order.success', ['order_number' => $order->order_number])
                ->with('success', 'Your Cash on Delivery order has been confirmed!');
        }

        // Default: order success page with tracking
        return redirect()->route('shop.order.success', ['order_number' => $order->order_number])
            ->with('success', 'Order created successfully! Proceed with payment.');
    }
}
