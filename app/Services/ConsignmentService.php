<?php

namespace App\Services;

use App\Models\Consignment;
use App\Models\ConsignmentItem;
use App\Models\ConsignmentSettlement;
use App\Models\Product;
use App\Models\Reseller;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Consignment: goods go out unpaid, and we settle later.
 *
 * Stock physically leaves the shelf on issue. On settlement each unit must land
 * in exactly one bucket — sold, returned good, expired, damaged or missing —
 * and only the "returned good" bucket goes back into stock. Everything else is
 * a loss, valued at cost so the P&L stays honest.
 */
class ConsignmentService
{
    public function __construct(private NumberGenerator $numbers) {}

    /**
     * @param  array<int, array{product_id:int, quantity:int, unit_price?:float}>  $lines
     */
    public function issue(Reseller $seller, array $lines, array $attributes, ?User $by = null): Consignment
    {
        return DB::transaction(function () use ($seller, $lines, $attributes, $by) {
            $products = Product::whereIn('id', collect($lines)->pluck('product_id'))->get()->keyBy('id');

            $rows = [];
            $issuedQty = 0;
            $issuedValue = 0.0;

            foreach ($lines as $line) {
                $product = $products->get((int) $line['product_id']);
                $quantity = (int) ($line['quantity'] ?? 0);

                if (! $product || $quantity < 1) {
                    continue;
                }

                // Default to the wholesale rate: the seller keeps retail minus this.
                $unitPrice = isset($line['unit_price']) && $line['unit_price'] !== ''
                    ? (float) $line['unit_price']
                    : (float) $product->reseller_price;

                $rows[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'unit_price' => $unitPrice,
                    'retail_price' => (float) $product->retail_price,
                    'cost_price' => (float) $product->cost_price,
                    'quantity_issued' => $quantity,
                ];

                $issuedQty += $quantity;
                $issuedValue += $unitPrice * $quantity;

                // The goods are gone from our shelf the moment they are handed over.
                $product->decrement('stock', min($quantity, $product->stock));
            }

            abort_if($rows === [], 422, 'A consignment needs at least one product.');

            $consignment = Consignment::create([
                'consignment_number' => $this->numbers->consignmentNumber(),
                'reseller_id' => $seller->id,
                'status' => Consignment::STATUS_OPEN,
                'issued_on' => $attributes['issued_on'] ?? now()->toDateString(),
                'due_on' => $attributes['due_on'] ?? null,
                'issued_by' => $by?->id,
                'quantity_issued' => $issuedQty,
                'issued_value' => round($issuedValue, 2),
                'notes' => $attributes['notes'] ?? null,
            ]);

            $consignment->items()->createMany($rows);

            return $consignment->fresh(['items', 'reseller']);
        });
    }

    /**
     * Record one collection. Lines are keyed by consignment_item_id.
     *
     * @param  array<int, array{consignment_item_id:int, sold?:int, returned?:int, expired?:int, damaged?:int, missing?:int}>  $lines
     */
    public function settle(Consignment $consignment, array $lines, array $attributes, ?User $by = null): ConsignmentSettlement
    {
        abort_unless($consignment->isOpen(), 422, 'This consignment is already closed.');

        return DB::transaction(function () use ($consignment, $lines, $attributes, $by) {
            $items = $consignment->items()->get()->keyBy('id');

            $rows = [];
            $soldValue = 0.0;

            foreach ($lines as $line) {
                $item = $items->get((int) ($line['consignment_item_id'] ?? 0));

                if (! $item) {
                    continue;
                }

                $sold = max(0, (int) ($line['sold'] ?? 0));
                $returned = max(0, (int) ($line['returned'] ?? 0));
                $expired = max(0, (int) ($line['expired'] ?? 0));
                $damaged = max(0, (int) ($line['damaged'] ?? 0));
                $missing = max(0, (int) ($line['missing'] ?? 0));

                $accounted = $sold + $returned + $expired + $damaged + $missing;

                if ($accounted === 0) {
                    continue;
                }

                abort_if(
                    $accounted > $item->outstanding(),
                    422,
                    "{$item->product_name}: you are accounting for {$accounted} pcs but only {$item->outstanding()} are still out.",
                );

                $lineSold = round((float) $item->unit_price * $sold, 2);
                $lineLoss = round((float) $item->cost_price * ($expired + $damaged + $missing), 2);
                $soldValue += $lineSold;

                $rows[] = [
                    'consignment_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'quantity_sold' => $sold,
                    'quantity_returned' => $returned,
                    'quantity_expired' => $expired,
                    'quantity_damaged' => $damaged,
                    'quantity_missing' => $missing,
                    'unit_price' => (float) $item->unit_price,
                    'sold_value' => $lineSold,
                    'loss_value' => $lineLoss,
                ];

                // Only good stock earns its place back on the shelf.
                if ($returned > 0 && $item->product_id) {
                    Product::whereKey($item->product_id)->increment('stock', $returned);
                }
            }

            abort_if($rows === [], 422, 'Record at least one unit as sold, returned or written off.');

            $collected = isset($attributes['amount_collected']) && $attributes['amount_collected'] !== ''
                ? round((float) $attributes['amount_collected'], 2)
                : round($soldValue, 2);

            $settlement = ConsignmentSettlement::create([
                'receipt_number' => $this->numbers->receiptNumber(),
                'consignment_id' => $consignment->id,
                'settled_on' => $attributes['settled_on'] ?? now()->toDateString(),
                'sold_value' => round($soldValue, 2),
                'amount_collected' => $collected,
                'method' => $attributes['method'] ?? 'cash',
                'reference' => $attributes['reference'] ?? null,
                'is_final' => (bool) ($attributes['is_final'] ?? false),
                'recorded_by' => $by?->id,
                'notes' => $attributes['notes'] ?? null,
            ]);

            $settlement->items()->createMany($rows);

            $this->syncTotals($consignment->refresh());

            return $settlement->fresh('items');
        });
    }

    /** Recomputes every tally from the settlement lines on record. */
    public function syncTotals(Consignment $consignment): Consignment
    {
        $itemTotals = DB::table('consignment_settlement_items')
            ->join('consignment_settlements', 'consignment_settlements.id', '=', 'consignment_settlement_items.consignment_settlement_id')
            ->where('consignment_settlements.consignment_id', $consignment->id)
            ->groupBy('consignment_settlement_items.consignment_item_id')
            ->select(
                'consignment_settlement_items.consignment_item_id as item_id',
                DB::raw('SUM(quantity_sold) as sold'),
                DB::raw('SUM(quantity_returned) as returned'),
                DB::raw('SUM(quantity_expired) as expired'),
                DB::raw('SUM(quantity_damaged) as damaged'),
                DB::raw('SUM(quantity_missing) as missing'),
                DB::raw('SUM(consignment_settlement_items.sold_value) as sold_value'),
                DB::raw('SUM(consignment_settlement_items.loss_value) as loss_value'),
            )
            ->get()
            ->keyBy('item_id');

        $totals = [
            'quantity_sold' => 0, 'quantity_returned' => 0, 'quantity_expired' => 0,
            'quantity_damaged' => 0, 'quantity_missing' => 0,
        ];
        $soldValue = 0.0;
        $lossValue = 0.0;

        foreach ($consignment->items()->get() as $item) {
            $row = $itemTotals->get($item->id);

            $item->update([
                'quantity_sold' => (int) ($row->sold ?? 0),
                'quantity_returned' => (int) ($row->returned ?? 0),
                'quantity_expired' => (int) ($row->expired ?? 0),
                'quantity_damaged' => (int) ($row->damaged ?? 0),
                'quantity_missing' => (int) ($row->missing ?? 0),
                'sold_value' => round((float) ($row->sold_value ?? 0), 2),
            ]);

            $totals['quantity_sold'] += (int) ($row->sold ?? 0);
            $totals['quantity_returned'] += (int) ($row->returned ?? 0);
            $totals['quantity_expired'] += (int) ($row->expired ?? 0);
            $totals['quantity_damaged'] += (int) ($row->damaged ?? 0);
            $totals['quantity_missing'] += (int) ($row->missing ?? 0);
            $soldValue += (float) ($row->sold_value ?? 0);
            $lossValue += (float) ($row->loss_value ?? 0);
        }

        $collected = (float) $consignment->settlements()->sum('amount_collected');
        $closed = $consignment->settlements()->where('is_final', true)->exists();

        $consignment->update([
            ...$totals,
            'sold_value' => round($soldValue, 2),
            'loss_value' => round($lossValue, 2),
            'amount_collected' => round($collected, 2),
            'amount_due' => round(max($soldValue - $collected, 0), 2),
        ]);

        $consignment->refresh();

        // Close once every unit is accounted for, or when the owner says so.
        if ($consignment->isOpen() && ($closed || $consignment->isFullyAccounted())) {
            $consignment->update([
                'status' => Consignment::STATUS_SETTLED,
                'settled_at' => now(),
            ]);
        }

        return $consignment->refresh();
    }

    /** Cancels an open batch and puts everything still outstanding back on the shelf. */
    public function cancel(Consignment $consignment): Consignment
    {
        abort_unless($consignment->isOpen(), 422, 'Only an open consignment can be cancelled.');

        return DB::transaction(function () use ($consignment) {
            foreach ($consignment->items()->get() as $item) {
                if ($item->outstanding() > 0 && $item->product_id) {
                    Product::whereKey($item->product_id)->increment('stock', $item->outstanding());
                }
            }

            $consignment->update([
                'status' => Consignment::STATUS_CANCELLED,
                'settled_at' => now(),
            ]);

            return $consignment->refresh();
        });
    }

    /** The status stamp for the issue slip and settlement receipt. */
    public function stampFor(Consignment $consignment): array
    {
        if ($consignment->status === Consignment::STATUS_CANCELLED) {
            return ['label' => 'CANCELLED', 'tone' => 'muted-red'];
        }

        if ($consignment->isOpen()) {
            return ['label' => 'OUT ON CONSIGNMENT', 'tone' => 'amber'];
        }

        return (float) $consignment->amount_due > 0
            ? ['label' => 'SETTLED · BALANCE DUE', 'tone' => 'amber']
            : ['label' => 'SETTLED IN FULL', 'tone' => 'green'];
    }
}
