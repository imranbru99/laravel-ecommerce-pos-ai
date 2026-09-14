@extends('layouts.app')

@section('title', 'Order Confirmed | Storefront')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-16 text-center">
    <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl">
        ✓
    </div>
    <h1 class="text-3xl font-extrabold text-gray-900">Thank You for Your Order!</h1>
    <p class="text-gray-600 mt-2">Your order has been successfully registered and is now being processed.</p>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mt-8 text-left">
        <div class="flex justify-between items-center border-b border-gray-100 pb-4 mb-4">
            <div>
                <span class="text-xs text-gray-500 font-semibold uppercase">Order Reference</span>
                <div class="text-xl font-bold text-gray-900">{{ $order->order_number }}</div>
            </div>
            <a href="{{ route('order.track', ['order_number' => $order->order_number]) }}" class="bg-indigo-50 text-indigo-600 hover:bg-indigo-100 px-4 py-2 rounded-lg text-xs font-semibold transition">
                Track Shipment →
            </a>
        </div>

        <div class="space-y-3">
            @foreach($order->items as $item)
                <div class="flex justify-between items-center text-sm">
                    <span class="text-gray-800">{{ $item->product_name }} <span class="text-gray-400 text-xs">(x{{ $item->quantity }})</span></span>
                    <span class="font-semibold text-gray-900">${{ number_format($item->subtotal, 2) }}</span>
                </div>
            @endforeach
        </div>

        <div class="border-t border-gray-100 mt-4 pt-4 flex justify-between font-bold text-base text-gray-900">
            <span>Total Paid / Due:</span>
            <span>${{ number_format($order->total, 2) }}</span>
        </div>
    </div>

    <div class="mt-8">
        <a href="{{ route('products.index') }}" class="inline-block bg-indigo-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-indigo-700 transition">
            Continue Shopping
        </a>
    </div>
</div>
@endsection
