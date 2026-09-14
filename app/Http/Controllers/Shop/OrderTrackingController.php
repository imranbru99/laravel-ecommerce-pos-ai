<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderTrackingController extends Controller
{
    /**
     * Display order tracking search page or tracking result.
     */
    public function index(Request $request): View
    {
        $order = null;
        $searched = false;

        if ($request->filled('order_number')) {
            $searched = true;
            $orderNumber = trim($request->input('order_number'));
            $contact = trim($request->input('contact', ''));

            $query = Order::with(['items.product', 'shippingAddress', 'statusHistories', 'shipment'])
                ->where('order_number', $orderNumber);

            if (!empty($contact)) {
                $query->whereHas('shippingAddress', function ($q) use ($contact) {
                    $q->where('phone', 'like', "%{$contact}%")
                      ->orWhere('email', $contact);
                });
            }

            $order = $query->first();
        }

        return view('shop.orders.track', compact('order', 'searched'));
    }

    /**
     * Show order success page after checkout.
     */
    public function success(string $order_number): View
    {
        $order = Order::with(['items.product.images', 'shippingAddress'])
            ->where('order_number', $order_number)
            ->firstOrFail();

        return view('shop.orders.success', compact('order'));
    }
}
