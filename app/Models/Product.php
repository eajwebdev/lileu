<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    protected $fillable = [
        'category_id', 'name', 'slug', 'sku', 'description', 'image_path',
        'retail_price', 'reseller_price', 'cost_price', 'stock', 'tracks_stock', 'low_stock_threshold',
        'min_reseller_qty', 'is_active', 'is_featured', 'available_to_resellers', 'sort_order',
    ];

    protected $casts = [
        'retail_price' => 'decimal:2',
        'reseller_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'tracks_stock' => 'boolean',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'available_to_resellers' => 'boolean',
    ];

    protected $appends = ['image_url', 'is_low_stock'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function resellers(): BelongsToMany
    {
        return $this->belongsToMany(Reseller::class)
            ->withPivot(['custom_price', 'is_approved'])
            ->withTimestamps();
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return str_starts_with($this->image_path, 'http')
            ? $this->image_path
            : Storage::url($this->image_path);
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->tracksStock() && $this->stock <= $this->low_stock_threshold;
    }

    public function tracksStock(): bool
    {
        return (bool) ($this->tracks_stock ?? true);
    }

    public function isAvailable(): bool
    {
        return ! $this->tracksStock() || $this->stock > 0;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
