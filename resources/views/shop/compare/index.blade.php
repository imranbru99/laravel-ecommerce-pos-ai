@extends('layouts.app')

@section('title', 'Product Comparison | Storefront')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-12 sm:px-6 lg:px-8">
    <div class="text-center mb-8">
        <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Product Comparison Matrix</h1>
        <p class="mt-2 text-sm text-gray-600">Compare features, specifications, pricing, and stock side-by-side.</p>
    </div>

    @if($products->isEmpty())
        <div class="bg-white rounded-2xl p-16 text-center border border-gray-100 shadow-sm max-w-lg mx-auto">
            <div class="text-5xl mb-3">⚖️</div>
            <h3 class="text-lg font-bold text-gray-900">Your Comparison List is Empty</h3>
            <p class="text-xs text-gray-500 mt-1">Browse our products and click the comparison icon to compare up to 4 items simultaneously.</p>
            <a href="{{ route('products.index') }}" class="mt-6 inline-block bg-indigo-600 text-white px-5 py-2.5 rounded-lg text-xs font-semibold hover:bg-indigo-700 transition">Browse Products</a>
        </div>
    @else
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50">
                        <th class="p-4 text-xs font-bold text-gray-400 uppercase w-48">Feature</th>
                        @foreach($products as $product)
                            <th class="p-4 text-center min-w-[220px]">
                                <div class="relative inline-block">
                                    <button onclick="removeCompare({{ $product->id }})" class="absolute -top-2 -right-2 bg-rose-500 hover:bg-rose-600 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs font-bold shadow">&times;</button>
                                    <div class="h-28 w-28 mx-auto bg-gray-100 rounded-xl overflow-hidden flex items-center justify-center mb-2">
                                        @if($product->primaryImage)
                                            <img src="{{ asset($product->primaryImage->path) }}" class="h-full w-full object-cover">
                                        @else
                                            <span class="text-2xl">📦</span>
                                        @endif
                                    </div>
                                    <div class="text-sm font-bold text-gray-900">{{ $product->name }}</div>
                                </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    <tr>
                        <td class="p-4 font-semibold text-gray-500 bg-gray-50">Price</td>
                        @foreach($products as $product)
                            <td class="p-4 text-center font-bold text-indigo-600 text-base">
                                ${{ number_format($product->effective_price, 2) }}
                            </td>
                        @endforeach
                    </tr>
                    <tr>
                        <td class="p-4 font-semibold text-gray-500 bg-gray-50">Brand</td>
                        @foreach($products as $product)
                            <td class="p-4 text-center text-gray-700">
                                {{ $product->brand?->name ?? '—' }}
                            </td>
                        @endforeach
                    </tr>
                    <tr>
                        <td class="p-4 font-semibold text-gray-500 bg-gray-50">Category</td>
                        @foreach($products as $product)
                            <td class="p-4 text-center text-gray-700">
                                {{ $product->categories->pluck('name')->join(', ') ?: '—' }}
                            </td>
                        @endforeach
                    </tr>
                    <tr>
                        <td class="p-4 font-semibold text-gray-500 bg-gray-50">Availability</td>
                        @foreach($products as $product)
                            <td class="p-4 text-center">
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold {{ $product->stock_status === 'in_stock' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                    {{ ucfirst(str_replace('_', ' ', $product->stock_status)) }}
                                </span>
                            </td>
                        @endforeach
                    </tr>
                    <tr>
                        <td class="p-4 font-semibold text-gray-500 bg-gray-50">Rating</td>
                        @foreach($products as $product)
                            <td class="p-4 text-center text-amber-500 font-bold">
                                ★ {{ number_format($product->average_rating, 1) }} ({{ $product->reviews_count }})
                            </td>
                        @endforeach
                    </tr>
                    <tr>
                        <td class="p-4 font-semibold text-gray-500 bg-gray-50">Action</td>
                        @foreach($products as $product)
                            <td class="p-4 text-center">
                                <form action="{{ route('cart.add') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold px-4 py-2 rounded-lg transition">Add to Cart</button>
                                </form>
                            </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    @endif
</div>

<script>
    async function removeCompare(id) {
        if (!confirm('Remove this product from comparison?')) return;
        const res = await fetch(`/compare/remove/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            }
        });
        location.reload();
    }
</script>
@endsection
