<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlashDealProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'flash_deal_id',
        'product_id',
        'discount',
        'discount_type',
        'purchase_limit',
    ];

    protected function casts(): array
    {
        return [
            'discount' => 'decimal:2',
            'purchase_limit' => 'integer',
        ];
    }

    public function flashDeal(): BelongsTo
    {
        return $this->belongsTo(FlashDeal::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
