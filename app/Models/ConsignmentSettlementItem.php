<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\NamesASoldItem;
use Illuminate\Database\Eloquent\Model;

class ConsignmentSettlementItem extends Model
{
    use NamesASoldItem;

    protected $fillable = [
        'consignment_settlement_id', 'consignment_item_id', 'product_id', 'product_variant_id', 'product_name', 'variant_name',
        'quantity_sold', 'quantity_returned', 'quantity_expired', 'quantity_damaged',
        'quantity_missing', 'unit_price', 'sold_value', 'loss_value',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'sold_value' => 'decimal:2',
        'loss_value' => 'decimal:2',
    ];

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(ConsignmentSettlement::class, 'consignment_settlement_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ConsignmentItem::class, 'consignment_item_id');
    }
}
