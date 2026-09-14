@extends('layouts.app')

@section('title', 'Shopping Cart | Storefront')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-12 sm:px-6 lg:px-8">
    <h1 class="text-3xl font-black text-gray-900 mb-8">Shopping Cart</h1>

    @if($cart->items->isEmpty())
        <div class="bg-white rounded-2xl p-16 text-center border border-gray-100 shadow-sm max-w-lg mx-auto">
            <div class="text-5xl mb-4">🛒</div>
            <h3 class="text-xl font-bold text-gray-900">Your Cart is Currently Empty</h3>
            <p class="text-xs text-gray-500 mt-2">Looks like you haven't added anything to your cart yet.</p>
            <a href="{{ route('products.index') }}" class="mt-6 inline-block bg-indigo-600 text-white px-6 py-2.5 rounded-xl text-xs font-bold hover:bg-indigo-700 transition shadow">
                Start Shopping
            </a>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Items Column -->
            <div class="lg:col-span-2 space-y-4">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm divide-y divide-gray-100 overflow-hidden">
                    @foreach($cart->items as $item)
                        <div class="p-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                            <div class="flex items-center gap-4 w-full sm:w-auto">
                                <div class="w-20 h-20 bg-gray-100 rounded-xl overflow-hidden shrink-0 flex items-center justify-center">
                                    @if($item->product->primaryImage)
                                        <img src="{{ asset($item->product->primaryImage->path) }}" class="w-full h-full object-cover">
                                    @else
                                        <span class="text-2xl">📦</span>
                                    @endif
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-gray-900">
                                        <a href="{{ route('products.show', $item->product->slug) }}" class="hover:text-indigo-600">
                                            {{ $item->product->name }}
                                        </a>
                                    </h3>
                                    @if($item->variant)
                                        <p class="text-xs text-gray-400">Variant: {{ $item->variant->sku }}</p>
                                    @endif
                                    <div class="text-indigo-600 font-bold text-sm mt-1">
                                        ${{ number_format($item->effective_price, 2) }}
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-6 w-full sm:w-auto justify-between sm:justify-end">
                                <!-- Qty selector -->
                                <form action="{{ route('cart.update', $item->id) }}" method="POST" class="flex items-center border rounded-lg overflow-hidden">
                                    @csrf
                                    @method('PATCH')
                                    <input type="number" name="quantity" value="{{ $item->quantity }}" min="1" onchange="this.form.submit()" class="w-16 text-center text-xs py-1.5 outline-none font-semibold">
                                </form>

                                <div class="text-right font-black text-gray-900 text-sm min-w-[70px]">
                                    ${{ number_format($item->subtotal, 2) }}
                                </div>

                                <form action="{{ route('cart.remove', $item->id) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-gray-400 hover:text-rose-600 transition text-sm" title="Remove item">
                                        ✕
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Order Summary Column -->
            <div class="space-y-6">
                <!-- Coupon Box -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-700 mb-3">Have a Promo Code?</h3>
                    <form action="{{ route('cart.coupon') }}" method="POST" class="flex gap-2">
                        @csrf
                        <input type="text" name="code" placeholder="Enter coupon" class="w-full text-xs uppercase font-semibold border rounded-lg px-3 py-2 outline-none focus:ring-1 focus:ring-indigo-500" required>
                        <button type="submit" class="bg-gray-900 hover:bg-indigo-600 text-white text-xs font-bold px-4 py-2 rounded-lg transition">Apply</button>
                    </form>
                </div>

                <!-- Totals -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-3">
                    <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-3">Order Summary</h3>
                    <div class="flex justify-between text-xs text-gray-600">
                        <span>Subtotal</span>
                        <span class="font-semibold">${{ number_format($cart->subtotal, 2) }}</span>
                    </div>

                    @if($cart->discount_amount > 0)
                        <div class="flex justify-between text-xs text-emerald-600 font-semibold">
                            <span>Discount Voucher</span>
                            <span>-${{ number_format($cart->discount_amount, 2) }}</span>
                        </div>
                    @endif

                    <div class="border-t border-gray-100 pt-3 flex justify-between text-base font-black text-gray-900">
                        <span>Total (Excl. Shipping)</span>
                        <span class="text-indigo-600">${{ number_format($cart->total, 2) }}</span>
                    </div>

                    <a href="{{ route('checkout.index') }}" class="mt-4 w-full block text-center bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded-xl text-sm transition shadow-md">
                        Proceed to Checkout →
                    </a>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
