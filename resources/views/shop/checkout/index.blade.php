@extends('layouts.app')

@section('title', 'Secure Checkout | Storefront')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-12 sm:px-6 lg:px-8">
    <h1 class="text-3xl font-black text-gray-900 mb-8">Secure Checkout</h1>

    <form action="{{ route('checkout.process') }}" method="POST">
        @csrf
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left 2 Cols: Shipping & Payment Options -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Customer Details -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                    <h2 class="text-base font-bold text-gray-900 mb-4">1. Shipping & Contact Information</h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">First Name *</label>
                            <input type="text" name="first_name" value="{{ old('first_name', $user?->name) }}" class="w-full text-xs border rounded-lg p-2.5 outline-none focus:ring-1 focus:ring-indigo-500" required>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Last Name *</label>
                            <input type="text" name="last_name" value="{{ old('last_name', '') }}" class="w-full text-xs border rounded-lg p-2.5 outline-none focus:ring-1 focus:ring-indigo-500" required>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Email Address *</label>
                            <input type="email" name="email" value="{{ old('email', $user?->email) }}" class="w-full text-xs border rounded-lg p-2.5 outline-none focus:ring-1 focus:ring-indigo-500" required>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Phone Number *</label>
                            <input type="text" name="phone" value="{{ old('phone', $user?->phone) }}" placeholder="e.g. 01700000000" class="w-full text-xs border rounded-lg p-2.5 outline-none focus:ring-1 focus:ring-indigo-500" required>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Street Address *</label>
                            <input type="text" name="address_line_1" value="{{ old('address_line_1', $defaultAddress?->address_line_1) }}" placeholder="House #, Street, Area" class="w-full text-xs border rounded-lg p-2.5 outline-none focus:ring-1 focus:ring-indigo-500" required>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">City *</label>
                            <input type="text" name="city" value="{{ old('city', $defaultAddress?->city ?? 'Dhaka') }}" class="w-full text-xs border rounded-lg p-2.5 outline-none focus:ring-1 focus:ring-indigo-500" required>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Postal Code *</label>
                            <input type="text" name="postal_code" value="{{ old('postal_code', $defaultAddress?->postal_code ?? '1212') }}" class="w-full text-xs border rounded-lg p-2.5 outline-none focus:ring-1 focus:ring-indigo-500" required>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Country *</label>
                            <select name="country" class="w-full text-xs border rounded-lg p-2.5 outline-none bg-white">
                                <option value="BD" selected>Bangladesh</option>
                                <option value="US">United States</option>
                                <option value="GB">United Kingdom</option>
                                <option value="CA">Canada</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Payment Method Selection -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                    <h2 class="text-base font-bold text-gray-900 mb-4">2. Select Payment Method</h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="border rounded-xl p-4 flex items-center gap-3 cursor-pointer hover:border-indigo-500 transition">
                            <input type="radio" name="payment_method" value="cod" checked class="text-indigo-600">
                            <div>
                                <div class="text-xs font-bold text-gray-900">Cash on Delivery (COD)</div>
                                <div class="text-[11px] text-gray-500">Pay cash upon parcel delivery</div>
                            </div>
                        </label>

                        <label class="border rounded-xl p-4 flex items-center gap-3 cursor-pointer hover:border-indigo-500 transition">
                            <input type="radio" name="payment_method" value="bkash" class="text-indigo-600">
                            <div>
                                <div class="text-xs font-bold text-rose-600">bKash Payment</div>
                                <div class="text-[11px] text-gray-500">Instant mobile wallet checkout</div>
                            </div>
                        </label>

                        <label class="border rounded-xl p-4 flex items-center gap-3 cursor-pointer hover:border-indigo-500 transition">
                            <input type="radio" name="payment_method" value="nagad" class="text-indigo-600">
                            <div>
                                <div class="text-xs font-bold text-amber-600">Nagad Payment</div>
                                <div class="text-[11px] text-gray-500">Fast digital postal payment</div>
                            </div>
                        </label>

                        <label class="border rounded-xl p-4 flex items-center gap-3 cursor-pointer hover:border-indigo-500 transition">
                            <input type="radio" name="payment_method" value="stripe" class="text-indigo-600">
                            <div>
                                <div class="text-xs font-bold text-gray-900">Credit / Debit Card</div>
                                <div class="text-[11px] text-gray-500">Powered by Stripe Global</div>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Right Col: Summary & Place Order -->
            <div class="space-y-4">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
                    <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-3">Your Order</h3>

                    <div class="divide-y divide-gray-100 max-h-60 overflow-y-auto pr-1">
                        @foreach($cart->items as $item)
                            <div class="py-2.5 flex justify-between text-xs">
                                <div>
                                    <div class="font-semibold text-gray-800">{{ $item->product->name }}</div>
                                    <div class="text-gray-400">Qty: {{ $item->quantity }}</div>
                                </div>
                                <div class="font-bold text-gray-900">${{ number_format($item->subtotal, 2) }}</div>
                            </div>
                        @endforeach
                    </div>

                    <div class="border-t border-gray-100 pt-3 space-y-2 text-xs">
                        <div class="flex justify-between text-gray-600">
                            <span>Subtotal</span>
                            <span>${{ number_format($cart->subtotal, 2) }}</span>
                        </div>
                        @if($cart->discount_amount > 0)
                            <div class="flex justify-between text-emerald-600 font-semibold">
                                <span>Discount</span>
                                <span>-${{ number_format($cart->discount_amount, 2) }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between text-gray-600">
                            <span>Shipping</span>
                            <span>${{ number_format($shippingRate, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-gray-600">
                            <span>Tax</span>
                            <span>${{ number_format($taxAmount, 2) }}</span>
                        </div>
                        <div class="border-t border-gray-100 pt-3 flex justify-between text-base font-black text-gray-900">
                            <span>Total Due</span>
                            <span class="text-indigo-600">${{ number_format($grandTotal, 2) }}</span>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded-xl text-sm transition shadow-lg mt-4">
                        Confirm & Place Order
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
