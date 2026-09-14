@extends('layouts.app')

@section('title', $product->name . ' | Storefront')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-12 sm:px-6 lg:px-8">
    <!-- Breadcrumbs -->
    <nav class="flex text-xs text-gray-500 mb-6 space-x-2">
        <a href="{{ route('home') }}" class="hover:text-indigo-600">Home</a>
        <span>/</span>
        <a href="{{ route('products.index') }}" class="hover:text-indigo-600">Products</a>
        <span>/</span>
        <span class="text-gray-900 font-semibold">{{ $product->name }}</span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 bg-white rounded-3xl border border-gray-100 shadow-sm p-6 sm:p-10 mb-12">
        <!-- Gallery Column -->
        <div>
            <div class="bg-gray-100 rounded-2xl h-96 flex items-center justify-center overflow-hidden mb-4 border border-gray-100">
                @if($product->primaryImage)
                    <img id="main-image" src="{{ asset($product->primaryImage->path) }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
                @else
                    <span class="text-6xl">🛍️</span>
                @endif
            </div>

            @if($product->images->count() > 1)
                <div class="flex gap-3 overflow-x-auto pb-2">
                    @foreach($product->images as $img)
                        <button onclick="document.getElementById('main-image').src='{{ asset($img->path) }}'" class="w-20 h-20 bg-gray-50 rounded-xl overflow-hidden border border-gray-200 hover:border-indigo-600 shrink-0">
                            <img src="{{ asset($img->path) }}" class="w-full h-full object-cover">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Product Details Column -->
        <div class="flex flex-col justify-between">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    @if($product->brand)
                        <span class="text-xs font-bold uppercase text-indigo-600 bg-indigo-50 px-2.5 py-1 rounded-md">{{ $product->brand->name }}</span>
                    @endif
                    <span class="text-xs font-semibold text-gray-400">SKU: {{ $product->sku ?? 'N/A' }}</span>
                </div>

                <h1 class="text-2xl sm:text-3xl font-black text-gray-900 leading-snug">{{ $product->name }}</h1>

                <!-- Rating -->
                <div class="flex items-center gap-2 mt-2">
                    <div class="text-amber-400 text-sm">★ ★ ★ ★ ★</div>
                    <span class="text-xs text-gray-500 font-medium">({{ $product->reviews_count }} verified reviews)</span>
                </div>

                <!-- Price -->
                <div class="mt-4 flex items-baseline gap-3">
                    <span class="text-3xl font-black text-indigo-600">${{ number_format($product->effective_price, 2) }}</span>
                    @if($product->is_on_sale)
                        <span class="text-lg text-gray-400 line-through">${{ number_format($product->price, 2) }}</span>
                        <span class="text-xs font-bold text-rose-600 bg-rose-50 px-2 py-1 rounded">Save {{ $product->discount_percentage }}%</span>
                    @endif
                </div>

                <p class="mt-4 text-xs sm:text-sm text-gray-600 leading-relaxed">
                    {{ $product->short_description ?? 'High quality premium product curated for our store catalog.' }}
                </p>

                <!-- Stock availability -->
                <div class="mt-6 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full {{ $product->stock_status === 'in_stock' ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                    <span class="text-xs font-semibold {{ $product->stock_status === 'in_stock' ? 'text-emerald-700' : 'text-rose-700' }}">
                        {{ $product->stock_status === 'in_stock' ? 'In Stock & Ready to Ship' : 'Currently Out of Stock' }}
                    </span>
                </div>

                <!-- Variants selection if present -->
                @if($product->variants->isNotEmpty())
                    <div class="mt-6">
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Select Variant:</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach($product->variants as $variant)
                                <label class="border rounded-lg px-3 py-2 text-xs font-semibold cursor-pointer hover:border-indigo-600 flex items-center gap-2">
                                    <input type="radio" name="variant_id" value="{{ $variant->id }}" {{ $loop->first ? 'checked' : '' }}>
                                    <span>{{ $variant->sku }} (${{ number_format($variant->effective_price, 2) }})</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <!-- Add to Cart Form -->
            <form action="{{ route('cart.add') }}" method="POST" class="mt-8 border-t border-gray-100 pt-6">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <div class="flex gap-4">
                    <div class="w-24">
                        <label class="block text-[10px] uppercase font-bold text-gray-400 mb-1">Quantity</label>
                        <input type="number" name="quantity" value="1" min="1" max="{{ $product->stock ?: 99 }}" class="w-full text-center text-sm border rounded-xl py-2.5 font-bold outline-none">
                    </div>
                    <div class="flex-1 flex items-end">
                        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-6 rounded-xl text-sm transition shadow-lg flex items-center justify-center gap-2">
                            <span>Add to Shopping Cart</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Long Description & Specs -->
    @if($product->description)
        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-8 mb-12">
            <h2 class="text-lg font-black text-gray-900 uppercase tracking-wide mb-4">Detailed Description & Specifications</h2>
            <div class="text-sm text-gray-600 leading-relaxed space-y-4">
                {!! nl2br(e($product->description)) !!}
            </div>
        </div>
    @endif

    <!-- Related Products -->
    @if($relatedProducts->isNotEmpty())
        <div>
            <h2 class="text-xl font-black text-gray-900 mb-6">You May Also Like</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
                @foreach($relatedProducts as $rel)
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 hover:shadow-md transition">
                        <div class="h-40 bg-gray-100 rounded-xl overflow-hidden mb-3 flex items-center justify-center">
                            @if($rel->primaryImage)
                                <img src="{{ asset($rel->primaryImage->path) }}" class="w-full h-full object-cover">
                            @else
                                <span class="text-3xl">📦</span>
                            @endif
                        </div>
                        <h3 class="text-xs font-bold text-gray-900 line-clamp-1">
                            <a href="{{ route('products.show', $rel->slug) }}" class="hover:text-indigo-600">{{ $rel->name }}</a>
                        </h3>
                        <div class="text-sm font-black text-indigo-600 mt-1">${{ number_format($rel->effective_price, 2) }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
