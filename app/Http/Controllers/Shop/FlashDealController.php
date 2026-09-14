<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\FlashDeal;
use Illuminate\View\View;

class FlashDealController extends Controller
{
    /**
     * Display all active and upcoming flash deals campaigns.
     */
    public function index(): View
    {
        $flashDeals = FlashDeal::with(['products.primaryImage'])
            ->where('status', true)
            ->where('end_date', '>=', now())
            ->orderBy('start_date')
            ->get();

        return view('shop.flash_deals.index', compact('flashDeals'));
    }

    /**
     * Display a specific flash deal campaign with deals and countdown.
     */
    public function show(string $slug): View
    {
        $flashDeal = FlashDeal::with([
            'products' => function ($query) {
                $query->with(['primaryImage', 'brand'])->active();
            }
        ])
        ->where('slug', $slug)
        ->where('status', true)
        ->firstOrFail();

        return view('shop.flash_deals.show', compact('flashDeal'));
    }
}
