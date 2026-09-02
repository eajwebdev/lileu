<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Consignment extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_SETTLED = 'settled';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'consignment_number', 'reseller_id', 'status', 'issued_on', 'due_on', 'settled_at', 'issued_by',
        'quantity_issued', 'quantity_sold', 'quantity_returned', 'quantity_expired',
        'quantity_damaged', 'quantity_missing',
        'issued_value', 'sold_value', 'loss_value', 'amount_collected', 'amount_due', 'notes',
    ];

    protected $casts = [
        'issued_on' => 'date',
        'due_on' => 'date',
        'settled_at' => 'datetime',
        'issued_value' => 'decimal:2',
        'sold_value' => 'decimal:2',
        'loss_value' => 'decimal:2',
        'amount_collected' => 'decimal:2',
        'amount_due' => 'decimal:2',
    ];

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ConsignmentItem::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(ConsignmentSettlement::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /** Units handed out that are still unaccounted for. */
    public function outstandingQuantity(): int
    {
        return max(0, $this->quantity_issued
            - $this->quantity_sold
            - $this->quantity_returned
            - $this->quantity_expired
            - $this->quantity_damaged
            - $this->quantity_missing);
    }

    public function isFullyAccounted(): bool
    {
        return $this->outstandingQuantity() === 0;
    }
}
