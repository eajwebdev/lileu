<?php

namespace App\Services;

use App\Contracts\Sellable;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Reseller;
use App\Models\ResellerOrder;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private NumberGenerator $numbers,
        private SellableResolver $sellables,
    ) {}

    /**
     * @param  array<int, array{product_id:int, product_variant_id?:int|null, quantity:int}>  $lines
     */
    public function placeResellerOrder(Reseller $reseller, array $lines, array $attributes): ResellerOrder
    {
        return DB::transaction(function () use ($reseller, $lines, $attributes) {
            $sellables = $this->sellables->resolve($lines);

            $subtotal = 0.0;
            $rows = [];

            foreach ($lines as $line) {
                $sellable = $sellables->get($this->sellables->keyForLine($line));

                if (! $sellable || ! $this->isOrderable($sellable)) {
                    continue;
                }

                $quantity = max(1, (int) $line['quantity']);
                $unitPrice = $this->priceFor($reseller, $sellable);
                $lineTotal = round($unitPrice * $quantity, 2);
                $subtotal += $lineTotal;

                $rows[] = [
                    'product_id' => $sellable->sellableProductId(),
                    'product_variant_id' => $sellable->sellableVariantId(),
                    'product_name' => $sellable->sellableProductName(),
                    'variant_name' => $sellable->sellableVariantName(),
                    'sku' => $sellable->sellableSku(),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            abort_if($rows === [], 422, 'Your order needs at least one available product.');

            $discount = round($subtotal * ($reseller->discount_percent / 100), 2);
            $deliveryFee = (float) ($attributes['delivery_fee'] ?? 0);
            $total = round($subtotal - $discount + $deliveryFee, 2);
            $downpaymentPercent = (int) ($attributes['downpayment_percent'] ?? $reseller->downpayment_percent);
            $downpaymentRequired = round($total * ($downpaymentPercent / 100), 2);

            $order = ResellerOrder::create([
                'order_number' => $this->numbers->resellerOrderNumber(),
                'reseller_id' => $reseller->id,
                'status' => ResellerOrder::STATUS_PENDING,
                'payment_status' => ResellerOrder::PAY_UNPAID,
                'fulfillment_type' => $attributes['fulfillment_type'] ?? 'pickup',
                'delivery_address' => $attributes['delivery_address'] ?? null,
                'date_needed' => $attributes['date_needed'] ?? null,
                'time_needed' => $attributes['time_needed'] ?? null,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'delivery_fee' => $deliveryFee,
                'total' => $total,
                'downpayment_percent' => $downpaymentPercent,
                'downpayment_required' => $downpaymentRequired,
                'amount_paid' => 0,
                'balance' => $total,
                'notes' => $attributes['notes'] ?? null,
            ]);

            $order->items()->createMany($rows);

            return $order->fresh(['items', 'reseller']);
        });
    }

    /**
     * Negotiated pivot price beats the standard reseller price. The deal is
     * struck per product, so every flavour of it inherits the same rate.
     */
    public function priceFor(Reseller $reseller, Sellable $sellable): float
    {
        $pivot = $reseller->products()
            ->where('products.id', $sellable->sellableProductId())
            ->first()?->pivot;

        if ($pivot && $pivot->custom_price !== null) {
            return (float) $pivot->custom_price;
        }

        return $sellable->resellerPrice();
    }

    /** A dormant product, or a flavour of one, cannot be ordered. */
    private function isOrderable(Sellable $sellable): bool
    {
        $product = $sellable instanceof Product
            ? $sellable
            : Product::find($sellable->sellableProductId());

        return (bool) $product?->is_active;
    }

    /**
     * Recomputes money + payment status from the paid payments on record.
     * Receipts are only ever a view of this — regenerating one never posts again.
     */
    public function syncTotals(ResellerOrder $order): ResellerOrder
    {
        $paid = (float) $order->payments()->where('status', Payment::STATUS_PAID)->sum('amount');
        $total = (float) $order->total;
        $balance = round(max($total - $paid, 0), 2);

        $status = match (true) {
            $order->status === ResellerOrder::STATUS_CANCELLED => ResellerOrder::PAY_VOID,
            $paid <= 0 => ResellerOrder::PAY_UNPAID,
            $paid + 0.005 >= $total => ResellerOrder::PAY_FULL,
            $paid + 0.005 >= (float) $order->downpayment_required => ResellerOrder::PAY_DOWNPAYMENT,
            default => ResellerOrder::PAY_PARTIAL,
        };

        $order->update([
            'amount_paid' => round($paid, 2),
            'balance' => $balance,
            'payment_status' => $status,
        ]);

        return $order->refresh();
    }
}
