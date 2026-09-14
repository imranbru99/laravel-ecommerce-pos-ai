<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'brand_id',
        'name',
        'slug',
        'sku',
        'short_description',
        'description',
        'price',
        'sale_price',
        'sale_starts_at',
        'sale_ends_at',
        'stock',
        'low_stock_threshold',
        'stock_status',
        'manage_stock',
        'weight',
        'length',
        'width',
        'height',
        'type',
        'meta_title',
        'meta_description',
        'is_active',
        'is_featured',
        'is_taxable',
        'tax_rate',
        'views',
        'sales_count',
        'average_rating',
        'reviews_count',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'sale_starts_at' => 'datetime',
            'sale_ends_at' => 'datetime',
            'stock' => 'integer',
            'low_stock_threshold' => 'integer',
            'manage_stock' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'is_taxable' => 'boolean',
            'tax_rate' => 'decimal:2',
            'average_rating' => 'decimal:2',
            'views' => 'integer',
            'sales_count' => 'integer',
            'reviews_count' => 'integer',
        ];
    }

    // Relationships
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_product');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'product_tag');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->hasMany(Review::class)->where('status', 'approved');
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function flashDeals(): BelongsToMany
    {
        return $this->belongsToMany(FlashDeal::class, 'flash_deal_products')
            ->withPivot(['discount', 'discount_type', 'purchase_limit'])
            ->withTimestamps();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock_status', 'in_stock')
            ->where(function ($q) {
                $q->where('manage_stock', false)
                  ->orWhere('stock', '>', 0);
            });
    }

    // Accessors & Helpers
    public function getEffectivePriceAttribute(): float
    {
        if ($this->is_on_sale) {
            return (float) $this->sale_price;
        }

        return (float) $this->price;
    }

    public function getIsOnSaleAttribute(): bool
    {
        if (!$this->sale_price || $this->sale_price >= $this->price) {
            return false;
        }

        $now = now();
        if ($this->sale_starts_at && $now->lt($this->sale_starts_at)) {
            return false;
        }

        if ($this->sale_ends_at && $now->gt($this->sale_ends_at)) {
            return false;
        }

        return true;
    }

    public function getDiscountPercentageAttribute(): int
    {
        if (!$this->is_on_sale || $this->price <= 0) {
            return 0;
        }

        return (int) round((($this->price - $this->sale_price) / $this->price) * 100);
    }
}
