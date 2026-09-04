<?php

namespace App\Services;

use App\Models\Consignment;
use App\Models\ConsignmentItem;
use App\Models\ConsignmentSettlement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Reseller;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Consignment: goods go out unpaid, and we settle later.
 *
 * Stocked products leave inventory on issue; made-to-order products do not.
 * On settlement each unit lands in one outcome bucket, while only good returns
 * of stocked products go back into inventory.
 */
class ConsignmentService
{
    public function __construct(
        private NumberGenerator $numbers,
        private SellableResolver $sellables,
    ) {}

    /**
     * @param  array<int, array{product_id:int, product_variant_id?:int|null, quantity:int, unit_price?:float}>  $lines
     */
    public function issue(Reseller $seller, array $lines, array $attributes, ?User $by = null): Consignment
    {
        return DB::transaction(function () use ($seller, $lines, $attributes, $by) {
            abort_unless($seller->isApproved(), 422, 'Only an approved seller can receive consigned stock.');

            // A flavour is its own line, so one product may appear more than
            // once - but the same flavour twice would double-count stock.
            $keys = collect($lines)->map(fn ($line) => $this->sellables->keyForLine($line));

            abort_if(
                $keys->duplicates()->isNotEmpty(),
                422,
                'Each item can appear only once in a consignment.',
            );

            // Keep the stock check and deduction atomic when two people issue stock at once.
            $sellables = $this->sellables->resolve($lines, lock: true);

            $rows = [];
            $issuedQty = 0;
            $issuedValue = 0.0;

            foreach ($lines as $line) {
                $sellable = $sellables->get($this->sellables->keyForLine($line));
                $quantity = (int) ($line['quantity'] ?? 0);

                if (! $sellable || $quantity < 1) {
                    continue;
                }

                $label = $sellable->sellableLabel();

                abort_unless(
                    $sellable->isManuallyAvailable(),
                    422,
                    "{$label} is currently unavailable.",
                );

                $onHand = $sellable->availableStock();

                abort_if(
                    $sellable->tracksStock() && $quantity > $onHand,
                    422,
                    "{$label}: only {$onHand} pcs are available, but {$quantity} were requested.",
                );

                // Default to the wholesale rate: the seller keeps retail minus this.
                $unitPrice = isset($line['unit_price']) && $line['unit_price'] !== ''
                    ? (float) $line['unit_price']
                    : $sellable->resellerPrice();

                $rows[] = [
                    'product_id' => $sellable->sellableProductId(),
                    'product_variant_id' => $sellable->sellableVariantId(),
                    'product_name' => $sellable->sellableProductName(),
                    'variant_name' => $sellable->sellableVariantName(),
                    'sku' => $sellable->sellableSku(),
                    'unit_price' => $unitPrice,
                    'retail_price' => $sellable->retailPrice(),
                    'cost_price' => $sellable->costPrice(),
                    'tracks_stock' => $sellable->tracksStock(),
                    'quantity_issued' => $quantity,
                ];

                $issuedQty += $quantity;
                $issuedValue += $unitPrice * $quantity;

                $sellable->decrementStock($quantity);
            }

            abort_if($rows === [], 422, 'A consignment needs at least one item.');

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
        return DB::transaction(function () use ($consignment, $lines, $attributes, $by) {
            $consignment = Consignment::query()->lockForUpdate()->findOrFail($consignment->id);
            abort_unless($consignment->isOpen(), 422, 'This consignment is already closed.');

            $lineIds = collect($lines)
                ->pluck('consignment_item_id')
                ->map(fn ($id) => (int) $id)
                ->filter();

            abort_if(
                $lineIds->duplicates()->isNotEmpty(),
                422,
                'Each consignment item can appear only once in a collection.',
            );

            $items = $consignment->items()->lockForUpdate()->get()->keyBy('id');

            $rows = [];
            $soldValue = 0.0;
            $remainingAfterCollection = $items->sum(fn (ConsignmentItem $item) => $item->outstanding());

            foreach ($lines as $line) {
                $item = $items->get((int) ($line['consignment_item_id'] ?? 0));

                abort_unless($item, 422, 'A collection line does not belong to this consignment.');

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
                $remainingAfterCollection -= $accounted;

                $rows[] = [
                    'consignment_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'product_name' => $item->product_name,
                    'variant_name' => $item->variant_name,
                    'quantity_sold' => $sold,
                    'quantity_returned' => $returned,
                    'quantity_expired' => $expired,
                    'quantity_damaged' => $damaged,
                    'quantity_missing' => $missing,
                    'unit_price' => (float) $item->unit_price,
                    'sold_value' => $lineSold,
                    'loss_value' => $lineLoss,
                ];

                // Only good stock earns its place back on the shelf, and it
                // returns to the flavour it left as.
                if ($returned > 0 && $item->tracks_stock) {
                    if ($item->product_variant_id) {
                        ProductVariant::whereKey($item->product_variant_id)->increment('stock', $returned);
                    } elseif ($item->product_id) {
                        Product::whereKey($item->product_id)->increment('stock', $returned);
                    }
                }
            }

            abort_if($rows === [], 422, 'Record at least one unit as sold, returned or written off.');
            abort_if(
                (bool) ($attributes['is_final'] ?? false) && $remainingAfterCollection > 0,
                422,
                "This batch still has {$remainingAfterCollection} unaccounted units. Record them as returned, expired, damaged, or missing/other before closing.",
            );

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

    /** Cancel an open batch and restore outstanding tracked stock. */
    public function cancel(Consignment $consignment): Consignment
    {
        abort_unless($consignment->isOpen(), 422, 'Only an open consignment can be cancelled.');

        return DB::transaction(function () use ($consignment) {
            foreach ($consignment->items()->get() as $item) {
                if ($item->outstanding() < 1 || ! $item->tracks_stock) {
                    continue;
                }

                // Back to the flavour it left as, never to the parent product.
                if ($item->product_variant_id) {
                    ProductVariant::whereKey($item->product_variant_id)
                        ->increment('stock', $item->outstanding());
                } elseif ($item->product_id) {
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
