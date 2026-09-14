<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductApiController;
use App\Http\Controllers\Api\AiApiController;
use App\Http\Controllers\Api\PosApiController;

/*
|--------------------------------------------------------------------------
| API Routes (v1)
|--------------------------------------------------------------------------
| RESTful endpoints for headless storefront, mobile app, AI tools & POS.
*/

Route::prefix('v1')->group(function () {
    // Products Catalog
    Route::get('/products', [ProductApiController::class, 'index']);
    Route::get('/products/{slug}', [ProductApiController::class, 'show']);

    // AI Automation Suite
    Route::prefix('ai')->group(function () {
        Route::post('/generate-description', [AiApiController::class, 'generateDescription']);
        Route::post('/generate-seo', [AiApiController::class, 'generateSeo']);
        Route::post('/chat', [AiApiController::class, 'chat']);
    });

    // Point of Sale (POS) Cashier Terminal
    Route::prefix('pos')->group(function () {
        Route::get('/products', [PosApiController::class, 'searchProducts']);
        Route::post('/checkout', [PosApiController::class, 'checkout']);
    });
});
