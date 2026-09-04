<?php

namespace App\Models;

use App\Contracts\Sellable;
use App\Support\ProductImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A flavour of a product, holding its own price and stock.
 */
class ProductVariant extends Model implements Sellable
{
    protected $fillable = [
        'product_id', 'name', 'slug', 'sku', 'description', 'image_path',
        'retail_price', 'reseller_price', 'cost_price',
        'stock', 'tracks_stock', 'low_stock_threshold',
        'is_active', 'is_available', 'sort_order',
    ];

    protected $casts = [
        'retail_price' => 'decimal:2',
        'reseller_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'tracks_stock' => 'boolean',
        'is_active' => 'boolean',
        'is_available' => 'boolean',
    ];

    protected $appends = ['image_url', 'is_low_stock'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Falls back to the parent product's picture when the flavour has none. */
    public function getImageUrlAttribute(): ?string
    {
        return ProductImage::url($this->image_path ?: $this->product?->image_path);
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->tracksStock() && $this->stock <= $this->low_stock_threshold;
    }

    public function isAvailable(): bool
    {
        return $this->is_active
            && $this->isManuallyAvailable()
            && (! $this->tracksStock() || $this->stock > 0);
    }

    /* ---------------------------------------------------------------- */
    /* Sellable                                                          */
    /* ---------------------------------------------------------------- */

    public function sellableProductId(): int
    {
        return $this->product_id;
    }

    public function sellableVariantId(): ?int
    {
        return $this->id;
    }

    public function sellableProductName(): string
    {
        return $this->product?->name ?? $this->name;
    }

    public function sellableVariantName(): ?string
    {
        return $this->name;
    }

    public function sellableLabel(): string
    {
        return trim($this->sellableProductName().' — '.$this->name);
    }

    public function sellableSku(): ?string
    {
        return $this->sku;
    }

    public function retailPrice(): float
    {
        return (float) $this->retail_price;
    }

    public function resellerPrice(): float
    {
        return (float) $this->reseller_price;
    }

    public function costPrice(): float
    {
        return (float) $this->cost_price;
    }

    public function availableStock(): int
    {
        return (int) $this->stock;
    }

    public function tracksStock(): bool
    {
        return (bool) ($this->tracks_stock ?? true);
    }

    public function isManuallyAvailable(): bool
    {
        return (bool) ($this->is_available ?? true);
    }

    public function decrementStock(int $quantity): void
    {
        if ($this->tracksStock()) {
            $this->decrement('stock', $quantity);
        }
    }

    public function incrementStock(int $quantity): void
    {
        if ($this->tracksStock()) {
            $this->increment('stock', $quantity);
        }
    }
}
