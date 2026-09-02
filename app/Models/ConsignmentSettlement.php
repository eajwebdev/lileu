<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class ConsignmentSettlement extends Model
{
    protected $fillable = [
        'receipt_number', 'consignment_id', 'settled_on', 'sold_value', 'amount_collected',
        'method', 'reference', 'is_final', 'recorded_by', 'notes',
    ];

    protected $casts = [
        'settled_on' => 'date',
        'sold_value' => 'decimal:2',
        'amount_collected' => 'decimal:2',
        'is_final' => 'boolean',
    ];

    public function consignment(): BelongsTo
    {
        return $this->belongsTo(Consignment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ConsignmentSettlementItem::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function methodLabel(): string
    {
        return match ($this->method) {
            'qrph' => 'QR Ph',
            'gcash' => 'GCash',
            'bank_transfer' => 'Bank Transfer',
            default => 'Cash',
        };
    }
}
