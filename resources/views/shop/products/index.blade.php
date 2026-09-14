@extends('layouts.app')

@section('title', 'Product Catalog & Store | Storefront')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-black text-gray-900">Explore Catalog</h1>
            <p class="text-xs text-gray-500 mt-1">Showing {{ $products->total() }} results</p>
        </div>

        <!-- Search & Sort Bar -->
        <form method="GET" action="{{ route('products.index') }}" class="flex flex-wrap items-center gap-3 w-full md:w-auto">
            <div class="relative flex-1 md:w-64">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Search products..." class="w-full text-xs border rounded-lg pl-8 pr-3 py-2 outline-none focus:ring-1 focus:ring-indigo-500">
                <svg class="w-4 h-4 text-gray-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>

            <select name="sort" onchange="this.form.submit()" class="text-xs border rounded-lg px-3 py-2 bg-white outline-none">
                <option value="latest" {{ request('sort') == 'latest' ? 'selected' : '' }}>Newest</option>
                <option value="price_asc" {{ request('sort') == 'price_asc' ? 'selected' : '' }}>Price: Low to High</option>
                <option value="price_desc" {{ request('sort') == 'price_desc' ? 'selected' : '' }}>Price: High to Low</option>
                <option value="popular" {{ request('sort') == 'popular' ? 'selected' : '' }}>Most Popular</option>
            </select>
        </form>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Sidebar Filters -->
        <div class="space-y-6">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-6">
                <!-- Categories -->
                <div>
                    <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider mb-3">Categories</h3>
                    <ul class="space-y-2 text-xs">
                        <li>
                            <a href="{{ route('products.index') }}" class="{{ !request('category') ? 'text-indigo-600 font-bold' : 'text-gray-600 hover:text-indigo-600' }}">
                                All Categories
                            </a>
                        </li>
                        @foreach($categories as $cat)
                            <li>
                                <a href="{{ route('products.index', ['category' => $cat->slug]) }}" class="{{ request('category') == $cat->slug ? 'text-indigo-600 font-bold' : 'text-gray-600 hover:text-indigo-600' }}">
                                    {{ $cat->name }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <!-- Brands -->
                @if($brands->isNotEmpty())
                    <div class="border-t border-gray-100 pt-5">
                        <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider mb-3">Brands</h3>
                        <ul class="space-y-2 text-xs">
                            @foreach($brands as $brand)
                                <li>
                                    <a href="{{ route('products.index', ['brand' => $brand->slug]) }}" class="{{ request('brand') == $brand->slug ? 'text-indigo-600 font-bold' : 'text-gray-600 hover:text-indigo-600' }}">
                                        {{ $brand->name }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>

        <!-- Product Grid -->
        <div class="lg:col-span-3">
            @if($products->isEmpty())
                <div class="bg-white rounded-2xl p-16 text-center border border-gray-100 shadow-sm">
                    <div class="text-4xl mb-3">📦</div>
                    <h3 class="text-base font-bold text-gray-900">No Products Found</h3>
                    <p class="text-xs text-gray-500 mt-1">Try adjusting your search criteria or filters.</p>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
                    @foreach($products as $product)
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden flex flex-col hover:shadow-md transition">
                            <div class="relative bg-gray-100 h-48 flex items-center justify-center overflow-hidden">
                                @if($product->primaryImage)
                                    <img src="{{ asset($product->primaryImage->path) }}" alt="{{ $product->name }}" class="h-full w-full object-cover">
                                @else
                                    <span class="text-4xl">🛍️</span>
                                @endif

                                @if($product->is_on_sale)
                                    <span class="absolute top-3 left-3 bg-rose-600 text-white text-[10px] font-bold px-2 py-0.5 rounded">
                                        SALE -{{ $product->discount_percentage }}%
                                    </span>
                                @endif

                                <!-- Quick Compare Icon -->
                                <button onclick="addToCompare({{ $product->id }})" class="absolute top-3 right-3 bg-white/80 hover:bg-white text-gray-700 p-1.5 rounded-full shadow-sm" title="Add to Compare">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                </button>
                            </div>

                            <div class="p-4 flex-1 flex flex-col justify-between">
                                <div>
                                    <span class="text-[10px] font-semibold text-gray-400 uppercase">{{ $product->brand?->name ?? 'Catalog' }}</span>
                                    <h3 class="text-sm font-bold text-gray-900 mt-1 line-clamp-1">
                                        <a href="{{ route('products.show', $product->slug) }}" class="hover:text-indigo-600">
                                            {{ $product->name }}
                                        </a>
                                    </h3>
                                    <div class="mt-2 flex items-baseline gap-2">
                                        <span class="text-base font-black text-indigo-600">${{ number_format($product->effective_price, 2) }}</span>
                                        @if($product->is_on_sale)
                                            <span class="text-xs text-gray-400 line-through">${{ number_format($product->price, 2) }}</span>
                                        @endif
                                    </div>
                                </div>

                                <form action="{{ route('cart.add') }}" method="POST" class="mt-4">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                                    <button type="submit" class="w-full bg-gray-900 hover:bg-indigo-600 text-white text-xs font-semibold py-2 px-3 rounded-lg transition">
                                        Add to Cart
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-8">
                    {{ $products->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

<script>
    async function addToCompare(productId) {
        try {
            const res = await fetch(`/compare/add/${productId}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                }
            });
            const data = await res.json();
            alert(data.message);
        } catch (e) {
            alert('Could not add to compare.');
        }
    }
</script>
@endsection
