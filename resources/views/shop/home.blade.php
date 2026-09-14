@extends('layouts.app')

@section('title', 'Welcome to Our Store')

@section('content')
    <div class="relative bg-gray-900 overflow-hidden">
        <div class="max-w-7xl mx-auto">
            <div class="relative z-10 pb-8 bg-gray-900 sm:pb-16 md:pb-20 lg:max-w-2xl lg:w-full lg:pb-28 xl:pb-32">
                <main class="mt-10 mx-auto max-w-7xl px-4 sm:mt-12 sm:px-6 md:mt-16 lg:mt-20 lg:px-8 xl:mt-28">
                    <div class="sm:text-center lg:text-left">
                        <h1 class="text-4xl tracking-tight font-extrabold text-white sm:text-5xl md:text-6xl">
                            <span class="block xl:inline">Premium products for</span>
                            <span class="block text-indigo-500 xl:inline">a modern lifestyle</span>
                        </h1>
                        <p class="mt-3 text-base text-gray-400 sm:mt-5 sm:text-lg sm:max-w-xl sm:mx-auto md:mt-5 md:text-xl lg:mx-0">
                            Discover our latest collection of high-quality items designed to elevate your everyday experience. Free shipping on orders over $100.
                        </p>
                        <div class="mt-5 sm:mt-8 sm:flex sm:justify-center lg:justify-start">
                            <div class="rounded-md shadow">
                                <a href="{{ route('products.index') }}" class="w-full flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 md:py-4 md:text-lg md:px-10 transition duration-150">
                                    Shop Now
                                </a>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>
        <div class="lg:absolute lg:inset-y-0 lg:right-0 lg:w-1/2">
            <img class="h-56 w-full object-cover sm:h-72 md:h-96 lg:w-full lg:h-full opacity-80" src="https://images.unsplash.com/photo-1441986300917-64674bd600d8?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" alt="Store Hero">
        </div>
    </div>

    @if($categories->count() > 0)
    <div class="bg-gray-50 py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-extrabold tracking-tight text-gray-900 mb-8">Shop by Category</h2>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-4">
                @foreach($categories as $category)
                    <a href="{{ route('categories.show', $category->slug) }}" class="group relative rounded-lg bg-white p-6 shadow-sm hover:shadow-md transition-shadow duration-200 flex flex-col items-center text-center">
                        <div class="h-16 w-16 mb-4 rounded-full bg-gray-100 flex items-center justify-center overflow-hidden">
                            @if($category->image)
                                <img src="{{ asset('storage/' . $category->image) }}" alt="{{ $category->name }}" class="h-full w-full object-cover">
                            @else
                                <svg class="h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 15l-4-4m0 0l-4 4m4-4v12"></path></svg>
                            @endif
                        </div>
                        <h3 class="text-sm font-semibold text-gray-900 group-hover:text-indigo-600 transition-colors">
                            {{ $category->name }}
                        </h3>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    @if($featuredProducts->count() > 0)
    <div class="bg-white py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between mb-8">
                <h2 class="text-2xl font-extrabold tracking-tight text-gray-900">Featured Products</h2>
                <a href="{{ route('products.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">View all <span aria-hidden="true">&rarr;</span></a>
            </div>

            <div class="grid grid-cols-1 gap-y-10 sm:grid-cols-2 gap-x-6 lg:grid-cols-4 xl:gap-x-8">
                @foreach($featuredProducts as $product)
                    <div class="group relative flex flex-col bg-white border border-gray-200 rounded-2xl overflow-hidden hover:shadow-lg transition-all duration-300">
                        <div class="aspect-w-1 aspect-h-1 bg-gray-200 overflow-hidden">
                            @php $primaryImage = $product->images->first(); @endphp
                            @if($primaryImage)
                                <img src="{{ asset('storage/' . $primaryImage->path) }}" alt="{{ $product->name }}" class="w-full h-64 object-center object-cover group-hover:scale-105 transition-transform duration-500">
                            @else
                                <div class="w-full h-64 bg-gray-100 flex items-center justify-center">
                                    <span class="text-gray-400">No image</span>
                                </div>
                            @endif

                            @if($product->sale_price)
                                <div class="absolute top-2 left-2 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded">SALE</div>
                            @endif
                        </div>
                        <div class="flex-1 p-4 space-y-2 flex flex-col justify-between">
                            <div>
                                <h3 class="text-sm font-medium text-gray-900 line-clamp-2">
                                    <a href="{{ route('products.show', $product->slug) }}">
                                        <span aria-hidden="true" class="absolute inset-0"></span>
                                        {{ $product->name }}
                                    </a>
                                </h3>
                            </div>
                            <div class="flex items-center justify-between mt-auto pt-4">
                                <div class="flex items-baseline space-x-2">
                                    @if($product->sale_price)
                                        <span class="text-lg font-bold text-gray-900">${{ number_format($product->sale_price, 2) }}</span>
                                        <span class="text-sm font-medium text-gray-500 line-through">${{ number_format($product->price, 2) }}</span>
                                    @else
                                        <span class="text-lg font-bold text-gray-900">${{ number_format($product->price, 2) }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <div class="bg-indigo-600">
        <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8 lg:flex lg:items-center lg:justify-between">
            <h2 class="text-3xl font-extrabold tracking-tight text-white md:text-4xl">
                <span class="block">Ready to upgrade your gear?</span>
                <span class="block text-indigo-200">Get 20% off your first order today.</span>
            </h2>
            <div class="mt-8 flex lg:mt-0 lg:flex-shrink-0">
                <div class="inline-flex rounded-md shadow">
                    <a href="#" class="inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-indigo-600 bg-white hover:bg-indigo-50">
                        Claim Discount
                    </a>
                </div>
            </div>
        </div>
    </div>

    @if($newArrivals->count() > 0)
    <div class="bg-white py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-extrabold tracking-tight text-gray-900 mb-8">New Arrivals</h2>
            <div class="grid grid-cols-1 gap-y-10 sm:grid-cols-2 gap-x-6 lg:grid-cols-4 xl:gap-x-8">
                @foreach($newArrivals as $product)
                    <div class="group relative flex flex-col bg-white border border-gray-200 rounded-2xl overflow-hidden hover:shadow-lg transition-all duration-300">
                        <div class="aspect-w-1 aspect-h-1 bg-gray-200 overflow-hidden">
                            @php $primaryImage = $product->images->first(); @endphp
                            @if($primaryImage)
                                <img src="{{ asset('storage/' . $primaryImage->path) }}" alt="{{ $product->name }}" class="w-full h-64 object-center object-cover group-hover:scale-105 transition-transform duration-500">
                            @else
                                <div class="w-full h-64 bg-gray-100 flex items-center justify-center">
                                    <span class="text-gray-400">No image</span>
                                </div>
                            @endif
                        </div>
                        <div class="flex-1 p-4 space-y-2 flex flex-col justify-between">
                            <div>
                                <h3 class="text-sm font-medium text-gray-900 line-clamp-2">
                                    <a href="{{ route('products.show', $product->slug) }}">
                                        <span aria-hidden="true" class="absolute inset-0"></span>
                                        {{ $product->name }}
                                    </a>
                                </h3>
                            </div>
                            <div class="flex items-center justify-between mt-auto pt-4">
                                <span class="text-lg font-bold text-gray-900">${{ number_format($product->sale_price ?? $product->price, 2) }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    @if($brands->count() > 0)
    <div class="bg-gray-50 py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <p class="text-center text-sm font-semibold uppercase text-gray-500 tracking-wide">
                Trusted by the best brands
            </p>
            <div class="mt-6 grid grid-cols-2 gap-8 md:grid-cols-4 lg:grid-cols-6">
                @foreach($brands as $brand)
                    <div class="col-span-1 flex justify-center md:col-span-2 lg:col-span-1 opacity-60 hover:opacity-100 transition-opacity">
                        @if($brand->logo)
                            <img class="h-12 object-contain" src="{{ asset('storage/' . $brand->logo) }}" alt="{{ $brand->name }}">
                        @else
                            <span class="text-xl font-bold text-gray-400">{{ $brand->name }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif
@endsection
