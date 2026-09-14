<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductApiController extends Controller
{
    /**
     * List active products with pagination, search and filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['primaryImage', 'brand', 'categories'])->active();

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($categoryId = $request->input('category_id')) {
            $query->whereHas('categories', function ($q) use ($categoryId) {
                $q->where('categories.id', $categoryId);
            });
        }

        if ($brandId = $request->input('brand_id')) {
            $query->where('brand_id', $brandId);
        }

        $perPage = min(50, (int) $request->input('per_page', 15));
        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $products->items(),
            'pagination' => [
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
            ],
        ]);
    }

    /**
     * Get single product detail.
     */
    public function show(string $slug): JsonResponse
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
        ->first();

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $product,
        ]);
    }
}
