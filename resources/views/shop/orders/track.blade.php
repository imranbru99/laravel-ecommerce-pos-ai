@extends('layouts.app')

@section('title', 'Track Your Order | Storefront')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-12 sm:px-6 lg:px-8">
    <div class="text-center mb-8">
        <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Real-Time Order Tracking</h1>
        <p class="mt-2 text-sm text-gray-600">Enter your order reference number and associated phone or email address.</p>
    </div>

    <!-- Tracking Search Form -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8">
        <form action="{{ route('order.track') }}" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Order Number *</label>
                <input type="text" name="order_number" value="{{ request('order_number') }}" placeholder="e.g. ORD-20260914-XXXX" class="w-full text-sm border rounded-lg px-3 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500" required>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Phone or Email (Optional)</label>
                <input type="text" name="contact" value="{{ request('contact') }}" placeholder="e.g. 01700000000 or customer@email.com" class="w-full text-sm border rounded-lg px-3 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-4 rounded-lg text-sm transition">
                    Track Order
                </button>
            </div>
        </form>
    </div>

    @if($searched)
        @if($order)
            <!-- Order Details Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-8">
                <div class="bg-indigo-50 border-b border-indigo-100 p-6 flex flex-wrap justify-between items-center gap-4">
                    <div>
                        <span class="text-xs font-semibold text-indigo-700 uppercase tracking-wider">Order Status</span>
                        <h2 class="text-2xl font-bold text-gray-900">{{ $order->order_number }}</h2>
                        <span class="text-xs text-gray-500">Placed on {{ $order->created_at->format('M d, Y h:i A') }}</span>
                    </div>
                    <div class="text-right">
                        <span class="inline-block px-3 py-1 rounded-full text-xs font-bold uppercase
                            @if($order->status === 'delivered') bg-emerald-100 text-emerald-800
                            @elseif($order->status === 'cancelled') bg-rose-100 text-rose-800
                            @else bg-amber-100 text-amber-800 @endif">
                            {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                        </span>
                        <div class="text-sm font-semibold text-gray-800 mt-1">Total: ${{ number_format($order->total, 2) }}</div>
                    </div>
                </div>

                <!-- Visual Progress Stepper -->
                @php
                    $steps = [
                        'pending' => 'Order Placed',
                        'confirmed' => 'Confirmed',
                        'processing' => 'Processing',
                        'shipped' => 'Dispatched / In Transit',
                        'out_for_delivery' => 'Out for Delivery',
                        'delivered' => 'Delivered'
                    ];

                    $statusOrder = array_keys($steps);
                    $currentIndex = array_search($order->status, $statusOrder);
                    if ($currentIndex === false) {
                        $currentIndex = 0;
                    }
                @endphp

                <div class="p-8">
                    <div class="relative">
                        <div class="hidden sm:block absolute top-1/2 left-0 right-0 h-1 bg-gray-200 -translate-y-1/2 -z-0"></div>
                        <div class="hidden sm:block absolute top-1/2 left-0 h-1 bg-indigo-600 -translate-y-1/2 transition-all duration-500 -z-0"
                             style="width: {{ count($statusOrder) > 1 ? ($currentIndex / (count($statusOrder) - 1)) * 100 : 0 }}%;"></div>

                        <div class="grid grid-cols-2 sm:grid-cols-6 gap-4 relative z-10">
                            @foreach($steps as $key => $label)
                                @php
                                    $stepIndex = array_search($key, $statusOrder);
                                    $isPassed = $stepIndex <= $currentIndex;
                                    $isCurrent = $stepIndex === $currentIndex;
                                @endphp
                                <div class="text-center">
                                    <div class="w-8 h-8 mx-auto rounded-full flex items-center justify-center font-bold text-xs
                                        {{ $isPassed ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-500' }}
                                        {{ $isCurrent ? 'ring-4 ring-indigo-100 animate-pulse' : '' }}">
                                        {{ $isPassed ? '✓' : $loop->iteration }}
                                    </div>
                                    <p class="mt-2 text-xs font-semibold {{ $isPassed ? 'text-indigo-900' : 'text-gray-400' }}">{{ $label }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Line Items Summary -->
                <div class="p-6 border-t border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-4">Package Contents</h3>
                    <div class="divide-y divide-gray-100">
                        @foreach($order->items as $item)
                            <div class="py-3 flex justify-between items-center text-sm">
                                <div>
                                    <span class="font-medium text-gray-800">{{ $item->product_name }}</span>
                                    <span class="text-gray-400 text-xs ml-2">x {{ $item->quantity }}</span>
                                </div>
                                <div class="font-semibold text-gray-900">${{ number_format($item->subtotal, 2) }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @else
            <div class="bg-white rounded-2xl p-12 text-center border border-gray-100 shadow-sm">
                <div class="text-4xl mb-3">🔍</div>
                <h3 class="text-lg font-bold text-gray-900">No matching order found</h3>
                <p class="text-sm text-gray-500 mt-1">Please double check your Order ID or phone/email address and try again.</p>
            </div>
        @endif
    @endif
</div>
@endsection
