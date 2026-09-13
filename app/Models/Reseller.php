<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Reseller extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SUSPENDED = 'suspended';

    /** How this seller works with us. Consignment sellers need no portal login. */
    public const ENGAGEMENT_RESELLER = 'reseller';
    public const ENGAGEMENT_CONSIGNMENT = 'consignment';
    public const ENGAGEMENT_BOTH = 'both';

    protected $fillable = [
        'user_id', 'code', 'name', 'business_name', 'email', 'phone', 'city', 'address',
        'facebook', 'why_reseller', 'status', 'engagement', 'discount_percent', 'downpayment_percent',
        'lifetime_value', 'admin_notes', 'applied_at', 'approved_at', 'approved_by',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
        'approved_at' => 'datetime',
        'lifetime_value' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(ResellerOrder::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function consignments(): HasMany
    {
        return $this->hasMany(Consignment::class);
    }

    /** The name we lead with: a business trades under one, a student does not. */
    public function displayName(): string
    {
        return $this->business_name ?: $this->name;
    }

    /**
     * The person behind the trading name, when that is not the same thing.
     * Shown beside the business name so a seller stays recognisable once
     * they fill one in.
     */
    public function personName(): ?string
    {
        return $this->business_name && $this->business_name !== $this->name
            ? $this->name
            : null;
    }

    public function takesConsignment(): bool
    {
        return in_array($this->engagement, [self::ENGAGEMENT_CONSIGNMENT, self::ENGAGEMENT_BOTH], true);
    }

    /** Cash still owed from consigned stock that has already sold. */
    public function consignmentDue(): float
    {
        return (float) $this->consignments()
            ->where('status', '!=', Consignment::STATUS_CANCELLED)
            ->sum('amount_due');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->withPivot(['custom_price', 'is_approved'])
            ->withTimestamps();
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /** Outstanding money across every open order. */
    public function outstandingBalance(): float
    {
        return (float) $this->orders()
            ->whereNotIn('status', [ResellerOrder::STATUS_CANCELLED])
            ->sum('balance');
    }
}
