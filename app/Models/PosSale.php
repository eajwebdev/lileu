<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class PosSale extends Model
{
    protected $fillable = [
        'sale_number', 'cashier_id', 'customer_name', 'subtotal', 'discount', 'total',
        'amount_tendered', 'change_due', 'method', 'status', 'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_tendered' => 'decimal:2',
        'change_due' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PosSaleItem::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }
}
