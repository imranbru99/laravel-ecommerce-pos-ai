<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCompare;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

class CompareController extends Controller
{
    /**
     * Display product comparison table.
     */
    public function index(): View
    {
        $userId = Auth::id();
        $sessionId = Session::getId();

        $query = ProductCompare::with(['product.primaryImage', 'product.brand', 'product.categories']);
        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('session_id', $sessionId);
        }

        $compares = $query->take(4)->get();
        $products = $compares->pluck('product');

        return view('shop.compare.index', compact('products'));
    }

    /**
     * Add product to compare list.
     */
    public function add(Request $request, int $id): JsonResponse
    {
        $userId = Auth::id();
        $sessionId = Session::getId();

        $existing = ProductCompare::where('product_id', $id)
            ->where(function ($q) use ($userId, $sessionId) {
                if ($userId) {
                    $q->where('user_id', $userId);
                } else {
                    $q->where('session_id', $sessionId);
                }
            })->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Product is already in your comparison list.',
            ]);
        }

        // Limit to 4 comparison items max
        $count = ProductCompare::where(function ($q) use ($userId, $sessionId) {
            if ($userId) {
                $q->where('user_id', $userId);
            } else {
                $q->where('session_id', $sessionId);
            }
        })->count();

        if ($count >= 4) {
            return response()->json([
                'success' => false,
                'message' => 'You can compare at most 4 products simultaneously.',
            ], 422);
        }

        ProductCompare::create([
            'user_id' => $userId,
            'session_id' => $sessionId,
            'product_id' => $id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product added to comparison.',
            'compare_count' => $count + 1,
        ]);
    }

    /**
     * Remove product from compare list.
     */
    public function remove(Request $request, int $id): JsonResponse
    {
        $userId = Auth::id();
        $sessionId = Session::getId();

        ProductCompare::where('product_id', $id)
            ->where(function ($q) use ($userId, $sessionId) {
                if ($userId) {
                    $q->where('user_id', $userId);
                } else {
                    $q->where('session_id', $sessionId);
                }
            })->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product removed from comparison.',
        ]);
    }
}
