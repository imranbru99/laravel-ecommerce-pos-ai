<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FlashDeal extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'start_date',
        'end_date',
        'status',
        'is_featured',
        'banner',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'status' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'flash_deal_products')
            ->withPivot(['discount', 'discount_type', 'purchase_limit'])
            ->withTimestamps();
    }

    public function dealProducts(): HasMany
    {
        return $this->hasMany(FlashDealProduct::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now());
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status && now()->between($this->start_date, $this->end_date);
    }
}
