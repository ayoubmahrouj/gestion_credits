<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'sku', 'barcode', 'name_fr', 'name_ar', 'description', 'brand_id', 'category_id', 'unit',
    'purchase_price', 'sale_price', 'tva', 'stock_qty', 'stock_min', 'is_active',
])]
class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'tva' => 'decimal:2',
            'stock_qty' => 'decimal:2',
            'stock_min' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected function name(): Attribute
    {
        return Attribute::get(fn () => app()->getLocale() === 'ar' && $this->name_ar
            ? $this->name_ar
            : $this->name_fr);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('stock_min', '>', 0)->whereColumn('stock_qty', '<=', 'stock_min');
    }

    public function isLowStock(): bool
    {
        return (float) $this->stock_min > 0 && (float) $this->stock_qty <= (float) $this->stock_min;
    }
}
