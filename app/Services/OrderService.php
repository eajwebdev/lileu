<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Product;
use App\Models\Reseller;
use App\Models\ResellerOrder;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(private NumberGenerator $numbers) {}

    /**
     * @param  array<int, array{product_id:int, quantity:int}>  $lines
     */
    public function placeResellerOrder(Reseller $reseller, array $lines, array $attributes): ResellerOrder
    {
        return DB::transaction(function () use ($reseller, $lines, $attributes) {
            $products = Product::query()
                ->whereIn('id', collect($lines)->pluck('product_id'))
                ->get()
                ->keyBy('id');

            $subtotal = 0.0;
            $rows = [];

            foreach ($lines as $line) {
                $product = $products->get((int) $line['product_id']);

                if (! $product || ! $product->is_active) {
                    continue;
                }

                $quantity = max(1, (int) $line['quantity']);
                $unitPrice = $this->priceFor($reseller, $product);
                $lineTotal = round($unitPrice * $quantity, 2);
                $subtotal += $lineTotal;

                $rows[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
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

    /** Negotiated pivot price beats the standard reseller price. */
    public function priceFor(Reseller $reseller, Product $product): float
    {
        $pivot = $reseller->products()->where('products.id', $product->id)->first()?->pivot;

        if ($pivot && $pivot->custom_price !== null) {
            return (float) $pivot->custom_price;
        }

        return (float) $product->reseller_price;
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
