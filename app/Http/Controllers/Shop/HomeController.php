<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Display the shop homepage with necessary data components.
     */
    public function index(Request $request): View
    {
        // 1. Fetch Top-Level Categories (Parent ID is null)
        $categories = Category::where('is_active', true)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->take(8)
            ->get();

        // 2. Fetch Featured Products with their Primary Image
        $featuredProducts = Product::where('is_active', true)
            ->where('is_featured', true)
            ->with(['images' => function ($query) {
                $query->where('is_primary', true);
            }])
            ->latest()
            ->take(8)
            ->get();

        // 3. Fetch New Arrivals (Latest active products)
        $newArrivals = Product::where('is_active', true)
            ->with(['images' => function ($query) {
                $query->where('is_primary', true);
            }])
            ->latest()
            ->take(8)
            ->get();

        // 4. Fetch Active Brands for the logo slider/grid
        $brands = Brand::where('is_active', true)
            ->orderBy('sort_order')
            ->take(12)
            ->get();

        // 5. If returning a Blade view:
        return view('shop.home', compact(
            'categories',
            'featuredProducts',
            'newArrivals',
            'brands'
        ));

        /* * If you are utilizing Next.js/React via Inertia.js instead of Blade,
         * swap the return statement to:
         * * return inertia('Shop/Home', [
         * 'categories' => $categories,
         * 'featuredProducts' => $featuredProducts,
         * 'newArrivals' => $newArrivals,
         * 'brands' => $brands
         * ]);
         */
    }
}
