<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'price',
        'sale_price',
        'stock',
        'weight',
        'image',
        'attribute_values',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'stock' => 'integer',
            'weight' => 'decimal:3',
            'attribute_values' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'product_attribute_value');
    }

    public function getEffectivePriceAttribute(): float
    {
        if ($this->sale_price && $this->sale_price > 0) {
            return (float) $this->sale_price;
        }

        if ($this->price && $this->price > 0) {
            return (float) $this->price;
        }

        return (float) $this->product->effective_price;
    }
}
