@extends('layouts.app')

@section('title', '🔥 Flash Deals & Limited-Time Campaigns | Storefront')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-12 sm:px-6 lg:px-8">
    <div class="text-center mb-10">
        <span class="text-xs font-bold uppercase tracking-widest text-rose-600 bg-rose-50 px-3 py-1 rounded-full">Limited Time Only</span>
        <h1 class="text-4xl font-extrabold text-gray-900 tracking-tight mt-2">Flash Deals & Mega Sales</h1>
        <p class="mt-2 text-sm text-gray-600">Exclusive discount events with countdown timers. Hurry before supplies run out!</p>
    </div>

    @if($flashDeals->isEmpty())
        <div class="bg-white rounded-2xl p-16 text-center border border-gray-100 shadow-sm">
            <div class="text-5xl mb-4">⏳</div>
            <h3 class="text-xl font-bold text-gray-900">No Active Flash Deals Right Now</h3>
            <p class="text-sm text-gray-500 mt-2">New campaigns are dropping soon. Stay tuned or browse our standard catalog.</p>
            <a href="{{ route('products.index') }}" class="mt-6 inline-block bg-indigo-600 text-white px-6 py-2.5 rounded-lg text-sm font-semibold hover:bg-indigo-700 transition">Explore Catalog</a>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            @foreach($flashDeals as $deal)
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden flex flex-col hover:shadow-md transition">
                    <div class="h-44 bg-gradient-to-r from-rose-500 to-indigo-600 p-6 flex flex-col justify-between text-white relative">
                        <div>
                            <span class="bg-white/20 backdrop-blur-sm text-xs font-bold px-2.5 py-1 rounded-full uppercase tracking-wider">⚡ Mega Deal</span>
                            <h2 class="text-2xl font-black mt-2 leading-tight">{{ $deal->title }}</h2>
                        </div>
                        <div class="text-xs opacity-90 font-medium">Ends: {{ $deal->end_date->format('M d, Y h:i A') }}</div>
                    </div>

                    <div class="p-6 flex-1 flex flex-col justify-between">
                        <div class="mb-4">
                            <span class="text-xs text-gray-500 font-semibold uppercase">Featured Items:</span>
                            <div class="flex -space-x-2 overflow-hidden mt-2">
                                @foreach($deal->products->take(5) as $product)
                                    <div class="inline-block h-10 w-10 rounded-full ring-2 ring-white bg-gray-100 overflow-hidden flex items-center justify-center text-xs font-bold text-gray-600">
                                        {{ substr($product->name, 0, 1) }}
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <a href="{{ route('flash-deals.show', $deal->slug) }}" class="w-full text-center bg-rose-600 hover:bg-rose-700 text-white font-semibold py-2.5 px-4 rounded-xl text-sm transition">
                            View Flash Deal →
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
