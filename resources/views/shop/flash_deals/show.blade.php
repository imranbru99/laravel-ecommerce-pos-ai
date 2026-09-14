@extends('layouts.app')

@section('title', $flashDeal->title . ' | Flash Deals')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-10 sm:px-6 lg:px-8">
    <!-- Hero Banner with Live Countdown Timer -->
    <div class="rounded-3xl bg-gradient-to-r from-rose-600 via-pink-600 to-indigo-700 text-white p-8 sm:p-12 mb-10 shadow-lg flex flex-col md:flex-row justify-between items-center gap-6">
        <div>
            <span class="bg-white/20 text-xs font-extrabold uppercase px-3 py-1 rounded-full tracking-wider">⚡ Live Flash Sale</span>
            <h1 class="text-3xl sm:text-5xl font-black mt-2 tracking-tight">{{ $flashDeal->title }}</h1>
            <p class="text-rose-100 mt-2 text-sm max-w-xl">Discounts applied automatically at checkout. Offer expires when the countdown reaches zero or stock exhausts!</p>
        </div>

        <!-- Countdown Box -->
        <div class="bg-black/30 backdrop-blur-md rounded-2xl p-5 text-center border border-white/10 min-w-[280px]">
            <div class="text-xs uppercase font-bold text-rose-200 mb-2">Deal Ends In</div>
            <div id="countdown" class="flex justify-center gap-3 text-center">
                <div class="bg-white/10 rounded-xl p-2.5 min-w-[55px]">
                    <span id="days" class="text-2xl font-black block leading-none">00</span>
                    <span class="text-[10px] text-gray-300 uppercase">Days</span>
                </div>
                <div class="bg-white/10 rounded-xl p-2.5 min-w-[55px]">
                    <span id="hours" class="text-2xl font-black block leading-none">00</span>
                    <span class="text-[10px] text-gray-300 uppercase">Hours</span>
                </div>
                <div class="bg-white/10 rounded-xl p-2.5 min-w-[55px]">
                    <span id="mins" class="text-2xl font-black block leading-none">00</span>
                    <span class="text-[10px] text-gray-300 uppercase">Mins</span>
                </div>
                <div class="bg-white/10 rounded-xl p-2.5 min-w-[55px]">
                    <span id="secs" class="text-2xl font-black block leading-none text-rose-300">00</span>
                    <span class="text-[10px] text-gray-300 uppercase">Secs</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        @foreach($flashDeal->products as $product)
            @php
                $discount = $product->pivot->discount;
                $discountType = $product->pivot->discount_type;
                $dealPrice = $discountType === 'percentage'
                    ? $product->price * (1 - ($discount / 100))
                    : max(0, $product->price - $discount);
            @endphp

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden flex flex-col hover:shadow-lg transition">
                <div class="relative bg-gray-100 h-52 flex items-center justify-center overflow-hidden">
                    @if($product->primaryImage)
                        <img src="{{ asset($product->primaryImage->path) }}" alt="{{ $product->name }}" class="h-full w-full object-cover">
                    @else
                        <span class="text-4xl">🛍️</span>
                    @endif
                    <span class="absolute top-3 left-3 bg-rose-600 text-white text-[11px] font-bold px-2 py-1 rounded-md">
                        -{{ $discountType === 'percentage' ? (int)$discount . '%' : '$' . (int)$discount }}
                    </span>
                </div>

                <div class="p-5 flex-1 flex flex-col justify-between">
                    <div>
                        <div class="text-xs text-gray-400 uppercase font-semibold">{{ $product->brand?->name ?? 'Special' }}</div>
                        <h3 class="text-base font-bold text-gray-900 mt-1 line-clamp-1">
                            <a href="{{ route('products.show', $product->slug) }}" class="hover:text-indigo-600">{{ $product->name }}</a>
                        </h3>

                        <div class="mt-2 flex items-baseline gap-2">
                            <span class="text-xl font-black text-rose-600">${{ number_format($dealPrice, 2) }}</span>
                            <span class="text-xs text-gray-400 line-through">${{ number_format($product->price, 2) }}</span>
                        </div>
                    </div>

                    <form action="{{ route('cart.add') }}" method="POST" class="mt-4">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        <input type="hidden" name="quantity" value="1">
                        <button type="submit" class="w-full bg-gray-900 hover:bg-rose-600 text-white text-xs font-bold py-2.5 px-4 rounded-xl transition flex items-center justify-center gap-2">
                            <span>Add to Cart</span>
                        </button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
</div>

<script>
    const endDate = new Date("{{ $flashDeal->end_date->toIso8601String() }}").getTime();

    function updateTimer() {
        const now = new Date().getTime();
        const diff = endDate - now;

        if (diff <= 0) {
            document.getElementById('countdown').innerHTML = "<span class='text-sm text-rose-300 font-bold'>Campaign Ended</span>";
            return;
        }

        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const secs = Math.floor((diff % (1000 * 60)) / 1000);

        document.getElementById('days').innerText = String(days).padStart(2, '0');
        document.getElementById('hours').innerText = String(hours).padStart(2, '0');
        document.getElementById('mins').innerText = String(mins).padStart(2, '0');
        document.getElementById('secs').innerText = String(secs).padStart(2, '0');
    }

    setInterval(updateTimer, 1000);
    updateTimer();
</script>
@endsection
