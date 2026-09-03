<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

/**
 * A thing the shop buys. Entered once, then reused on every purchase — the
 * last price paid rides along as the default so the buyer only touches it
 * when the supplier's price has actually moved.
 */
class Ingredient extends Model
{
    /** Units the shop actually buys in. */
    public const UNITS = ['pc', 'pack', 'box', 'kg', 'g', 'L', 'ml', 'can', 'bottle', 'sack', 'tray', 'dozen'];

    public const CATEGORIES = ['ingredient', 'packaging', 'supplies', 'other'];

    protected $fillable = [
        'name', 'slug', 'unit', 'category', 'supplier',
        'last_price', 'last_purchased_on', 'notes', 'is_active',
    ];

    protected $casts = [
        'last_price' => 'decimal:2',
        'last_purchased_on' => 'date',
        'is_active' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function priceChanges(): HasMany
    {
        return $this->hasMany(IngredientPriceChange::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
