<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * Display a listing of products with filters.
     */
    public function index(Request $request): View
    {
        $query = Product::with(['primaryImage', 'brand', 'categories'])->active();

        // Search
        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('short_description', 'like', "%{$search}%");
            });
        }

        // Category Filter
        if ($categorySlug = $request->input('category')) {
            $query->whereHas('categories', function ($q) use ($categorySlug) {
                $q->where('slug', $categorySlug);
            });
        }

        // Brand Filter
        if ($brandSlug = $request->input('brand')) {
            $query->whereHas('brand', function ($q) use ($brandSlug) {
                $q->where('slug', $brandSlug);
            });
        }

        // Price Filter
        if ($minPrice = $request->input('min_price')) {
            $query->where('price', '>=', (float) $minPrice);
        }
        if ($maxPrice = $request->input('max_price')) {
            $query->where('price', '<=', (float) $maxPrice);
        }

        // In Stock Only
        if ($request->boolean('in_stock')) {
            $query->inStock();
        }

        // Sorting
        $sort = $request->input('sort', 'latest');
        match ($sort) {
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'rating' => $query->orderBy('average_rating', 'desc'),
            'popular' => $query->orderBy('sales_count', 'desc'),
            default => $query->latest(),
        };

        $products = $query->paginate(16)->withQueryString();
        $categories = Category::active()->parents()->with('children')->get();
        $brands = Brand::active()->orderBy('name')->get();

        return view('shop.products.index', compact('products', 'categories', 'brands'));
    }

    /**
     * Display single product detail page.
     */
    public function show(string $slug): View
    {
        $product = Product::with([
            'images',
            'variants.attributeValues',
            'brand',
            'categories',
            'approvedReviews.user',
            'tags'
        ])
        ->where('slug', $slug)
        ->active()
        ->firstOrFail();

        // Increment views counter
        $product->increment('views');

        // Related Products from same category
        $relatedProducts = Product::active()
            ->where('id', '!=', $product->id)
            ->whereHas('categories', function ($q) use ($product) {
                $q->whereIn('categories.id', $product->categories->pluck('id'));
            })
            ->with('primaryImage')
            ->take(4)
            ->get();

        return view('shop.products.show', compact('product', 'relatedProducts'));
    }
}
