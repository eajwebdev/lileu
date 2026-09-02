<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    public const KIND_DOWNPAYMENT = 'downpayment';
    public const KIND_BALANCE = 'balance';
    public const KIND_FULL = 'full';
    public const KIND_ADJUSTMENT = 'adjustment';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_VOID = 'void';

    protected $fillable = [
        'receipt_number', 'reseller_order_id', 'kind', 'method', 'status', 'amount',
        'currency', 'reference', 'paymongo_source_id', 'paymongo_payment_id', 'qr_payload',
        'paid_at', 'expires_at', 'recorded_by', 'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
        'meta' => 'array',
    ];

    /** Never let gateway internals reach a receipt or an Inertia payload. */
    protected $hidden = ['qr_payload', 'meta'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ResellerOrder::class, 'reseller_order_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function methodLabel(): string
    {
        return match ($this->method) {
            'qrph' => 'QR Ph',
            'gcash' => 'GCash',
            'bank_transfer' => 'Bank Transfer',
            'cash' => 'Cash',
            default => 'Manual',
        };
    }

    public function kindLabel(): string
    {
        return match ($this->kind) {
            self::KIND_DOWNPAYMENT => 'Downpayment',
            self::KIND_BALANCE => 'Balance Payment',
            self::KIND_FULL => 'Full Payment',
            default => 'Adjustment',
        };
    }
}
