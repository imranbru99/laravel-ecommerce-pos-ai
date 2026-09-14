<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\ProductController;
use App\Http\Controllers\Shop\CategoryController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\FlashDealController;
use App\Http\Controllers\Shop\OrderTrackingController;
use App\Http\Controllers\Shop\CompareController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Homepage
Route::get('/', [HomeController::class, 'index'])->name('home');

// Products & Catalog
Route::prefix('products')->name('products.')->group(function () {
    Route::get('/', [ProductController::class, 'index'])->name('index');
    Route::get('/{slug}', [ProductController::class, 'show'])->name('show');
});

// Categories
Route::prefix('categories')->name('categories.')->group(function () {
    Route::get('/', [CategoryController::class, 'index'])->name('index');
    Route::get('/{slug}', [CategoryController::class, 'show'])->name('show');
});

// Flash Deals & Limited-Time Campaigns
Route::prefix('flash-deals')->name('flash-deals.')->group(function () {
    Route::get('/', [FlashDealController::class, 'index'])->name('index');
    Route::get('/{slug}', [FlashDealController::class, 'show'])->name('show');
});

// Shopping Cart & Utilities
Route::prefix('cart')->name('cart.')->group(function () {
    Route::get('/', [CartController::class, 'index'])->name('index');
    Route::post('/add', [CartController::class, 'add'])->name('add');
    Route::patch('/update/{id}', [CartController::class, 'update'])->name('update');
    Route::delete('/remove/{id}', [CartController::class, 'remove'])->name('remove');
    Route::post('/coupon', [CartController::class, 'applyCoupon'])->name('coupon');
    Route::get('/mini', [CartController::class, 'miniCart'])->name('mini');
});

// Product Comparison Matrix
Route::prefix('compare')->name('compare.')->group(function () {
    Route::get('/', [CompareController::class, 'index'])->name('index');
    Route::post('/add/{id}', [CompareController::class, 'add'])->name('add');
    Route::delete('/remove/{id}', [CompareController::class, 'remove'])->name('remove');
});

// Public Order Tracking
Route::get('/track-order', [OrderTrackingController::class, 'index'])->name('order.track');
Route::get('/orders/confirmed/{order_number}', [OrderTrackingController::class, 'success'])->name('shop.order.success');

// Checkout Flow
Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/checkout/process', [CheckoutController::class, 'process'])->name('checkout.process');

// Payment Callbacks
Route::prefix('payment')->name('payment.')->group(function () {
    Route::get('/bkash/callback', function () {
        return redirect()->route('home')->with('info', 'bKash transaction processed.');
    })->name('bkash.callback');

    Route::get('/nagad/callback', function () {
        return redirect()->route('home')->with('info', 'Nagad transaction processed.');
    })->name('nagad.callback');
});

// Authentication routes
require __DIR__.'/auth.php';
