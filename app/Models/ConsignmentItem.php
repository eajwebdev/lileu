<?php

namespace App\Models;

use App\Models\Concerns\NamesASoldItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsignmentItem extends Model
{
    use NamesASoldItem;

    protected $fillable = [
        'consignment_id', 'product_id', 'product_variant_id', 'product_name', 'variant_name', 'sku',
        'unit_price', 'retail_price', 'cost_price', 'tracks_stock',
        'quantity_issued', 'quantity_sold', 'quantity_returned',
        'quantity_expired', 'quantity_damaged', 'quantity_missing', 'sold_value',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'retail_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'tracks_stock' => 'boolean',
        'sold_value' => 'decimal:2',
    ];

    public function consignment(): BelongsTo
    {
        return $this->belongsTo(Consignment::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Units of this line still out in the field. */
    public function outstanding(): int
    {
        return max(0, $this->quantity_issued
            - $this->quantity_sold
            - $this->quantity_returned
            - $this->quantity_expired
            - $this->quantity_damaged
            - $this->quantity_missing);
    }
}
