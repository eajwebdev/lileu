<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class ResellerOrder extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PREPARING = 'preparing';
    public const STATUS_READY = 'ready';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const PAY_UNPAID = 'unpaid';
    public const PAY_PARTIAL = 'partially_paid';
    public const PAY_DOWNPAYMENT = 'downpayment_paid';
    public const PAY_FULL = 'fully_paid';
    public const PAY_REFUNDED = 'refunded';
    public const PAY_VOID = 'void';

    protected $fillable = [
        'order_number', 'reseller_id', 'status', 'payment_status', 'fulfillment_type',
        'delivery_address', 'date_needed', 'time_needed', 'subtotal', 'discount',
        'delivery_fee', 'total', 'downpayment_percent', 'downpayment_required',
        'amount_paid', 'balance', 'notes', 'admin_notes',
        'confirmed_at', 'completed_at', 'cancelled_at',
    ];

    protected $casts = [
        'date_needed' => 'date',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total' => 'decimal:2',
        'downpayment_required' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'balance' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ResellerOrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function paidPayments(): HasMany
    {
        return $this->payments()->where('status', Payment::STATUS_PAID);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function isFullyPaid(): bool
    {
        return $this->payment_status === self::PAY_FULL;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /** What the reseller still needs to hand over to move the order forward. */
    public function amountDueNow(): float
    {
        if ($this->amount_paid <= 0) {
            return round(min((float) $this->downpayment_required, (float) $this->total), 2);
        }

        return round((float) $this->balance, 2);
    }

    public function nextPaymentKind(): string
    {
        if ($this->amount_paid <= 0) {
            return $this->downpayment_percent >= 100 ? Payment::KIND_FULL : Payment::KIND_DOWNPAYMENT;
        }

        return Payment::KIND_BALANCE;
    }
}
