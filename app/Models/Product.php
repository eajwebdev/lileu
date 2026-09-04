<?php

namespace App\Models;

use App\Contracts\Sellable;
use App\Support\ProductImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A product either stands alone or comes in flavours.
 *
 * Standing alone, it carries its own price and stock and is sellable itself.
 * With variants, price and stock live on each flavour instead — the columns
 * here stop being the truth, and only a variant can be sold.
 */
class Product extends Model implements Sellable
{
    protected $fillable = [
        'category_id', 'name', 'slug', 'sku', 'description', 'image_path',
        'retail_price', 'reseller_price', 'cost_price', 'stock', 'tracks_stock', 'low_stock_threshold',
        'min_reseller_qty', 'is_active', 'is_available', 'is_featured', 'available_to_resellers', 'sort_order',
    ];

    protected $casts = [
        'retail_price' => 'decimal:2',
        'reseller_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'tracks_stock' => 'boolean',
        'is_active' => 'boolean',
        'is_available' => 'boolean',
        'is_featured' => 'boolean',
        'available_to_resellers' => 'boolean',
    ];

    protected $appends = ['image_url', 'is_low_stock'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('name');
    }

    public function activeVariants(): HasMany
    {
        return $this->variants()->where('is_active', true);
    }

    public function resellers(): BelongsToMany
    {
        return $this->belongsToMany(Reseller::class)
            ->withPivot(['custom_price', 'is_approved'])
            ->withTimestamps();
    }

    public function getImageUrlAttribute(): ?string
    {
        return ProductImage::url($this->image_path);
    }

    /* ---------------------------------------------------------------- */
    /* Variants                                                          */
    /* ---------------------------------------------------------------- */

    public function hasVariants(): bool
    {
        return $this->relationLoaded('variants')
            ? $this->variants->isNotEmpty()
            : $this->variants()->exists();
    }

    /** The flavours a customer can actually pick right now. */
    public function sellableVariants()
    {
        $variants = $this->relationLoaded('variants')
            ? $this->variants
            : $this->variants()->get();

        return $variants->filter(fn (ProductVariant $v) => $v->is_active);
    }

    /** Stock is the sum of the flavours once there are any. */
    public function totalStock(): int
    {
        if (! $this->hasVariants()) {
            return (int) $this->stock;
        }

        return (int) $this->sellableVariants()
            ->filter(fn (ProductVariant $v) => $v->tracksStock())
            ->sum('stock');
    }

    /** The cheapest sellable flavour, which is what "from ₱x" shows. */
    public function lowestRetailPrice(): float
    {
        if (! $this->hasVariants()) {
            return (float) $this->retail_price;
        }

        return (float) ($this->sellableVariants()->min('retail_price') ?? 0);
    }

    public function highestRetailPrice(): float
    {
        if (! $this->hasVariants()) {
            return (float) $this->retail_price;
        }

        return (float) ($this->sellableVariants()->max('retail_price') ?? 0);
    }

    public function lowestResellerPrice(): float
    {
        if (! $this->hasVariants()) {
            return (float) $this->reseller_price;
        }

        return (float) ($this->sellableVariants()->min('reseller_price') ?? 0);
    }

    public function getIsLowStockAttribute(): bool
    {
        if ($this->hasVariants()) {
            return $this->sellableVariants()->contains(fn (ProductVariant $v) => $v->is_low_stock);
        }

        return $this->tracksStock() && $this->stock <= $this->low_stock_threshold;
    }

    public function tracksStock(): bool
    {
        return (bool) ($this->tracks_stock ?? true);
    }

    /** In stock and switched on — for a product with flavours, if any flavour is. */
    public function isAvailable(): bool
    {
        if (! $this->isManuallyAvailable()) {
            return false;
        }

        if ($this->hasVariants()) {
            return $this->sellableVariants()->contains(fn (ProductVariant $v) => $v->isAvailable());
        }

        return ! $this->tracksStock() || $this->stock > 0;
    }

    public function isManuallyAvailable(): bool
    {
        return (bool) ($this->is_available ?? true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /* ---------------------------------------------------------------- */
    /* Sellable — only meaningful when the product has no variants       */
    /* ---------------------------------------------------------------- */

    public function sellableProductId(): int
    {
        return $this->id;
    }

    public function sellableVariantId(): ?int
    {
        return null;
    }

    public function sellableProductName(): string
    {
        return $this->name;
    }

    public function sellableVariantName(): ?string
    {
        return null;
    }

    public function sellableLabel(): string
    {
        return $this->name;
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
