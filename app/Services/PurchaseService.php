<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\IngredientPriceChange;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Buying ingredients.
 *
 * A line either points at an ingredient already in the catalog or names a new
 * one — either way the ingredient exists afterwards, so the next purchase is a
 * pick from the list rather than a re-typed name. The price on the line wins:
 * whatever the buyer corrects it to before saving becomes that ingredient's
 * new default, and the move is recorded so cost drift stays visible.
 */
class PurchaseService
{
    /**
     * @param  array<int, array{ingredient_id?:int|null, name?:string|null, unit?:string|null, quantity:float, unit_price:float}>  $lines
     */
    public function record(array $lines, array $attributes, ?User $by = null): Purchase
    {
        return DB::transaction(function () use ($lines, $attributes, $by) {
            $purchasedOn = Carbon::parse($attributes['purchased_on'] ?? now());
            $supplier = $attributes['supplier'] ?? null;

            $purchase = Purchase::create([
                'purchased_on' => $purchasedOn,
                'supplier' => $supplier,
                'reference' => $attributes['reference'] ?? null,
                'description' => $attributes['description'] ?? '',
                'notes' => $attributes['notes'] ?? null,
                'amount' => 0,
                'recorded_by' => $by?->id,
            ]);

            $total = 0.0;
            $names = [];

            foreach ($lines as $line) {
                $quantity = round((float) ($line['quantity'] ?? 0), 3);
                $unitPrice = round((float) ($line['unit_price'] ?? 0), 2);

                if ($quantity <= 0) {
                    continue;
                }

                $ingredient = $this->resolveIngredient($line);

                if (! $ingredient) {
                    continue;
                }

                $lineTotal = round($quantity * $unitPrice, 2);

                $purchase->items()->create([
                    'ingredient_id' => $ingredient->id,
                    'name' => $ingredient->name,
                    'unit' => $ingredient->unit,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ]);

                $this->applyPrice($ingredient, $unitPrice, $purchasedOn, $supplier, $purchase->id, $by);

                $total += $lineTotal;
                $names[] = $ingredient->name;
            }

            abort_if($names === [], 422, 'Add at least one ingredient before saving the purchase.');

            $purchase->update([
                'amount' => round($total, 2),
                // The ledger and reports read a one-line description, so build
                // one from the basket when the buyer did not write their own.
                'description' => $purchase->description ?: $this->summarise($names),
            ]);

            return $purchase->load('items');
        });
    }

    /** An existing catalog row, or a brand new one named on the line. */
    private function resolveIngredient(array $line): ?Ingredient
    {
        if (! empty($line['ingredient_id'])) {
            return Ingredient::find((int) $line['ingredient_id']);
        }

        $name = trim((string) ($line['name'] ?? ''));

        if ($name === '') {
            return null;
        }

        // Typing a name that already exists reuses that ingredient rather than
        // creating a near-duplicate the buyer would have to pick between later.
        $existing = Ingredient::whereRaw('LOWER(name) = ?', [Str::lower($name)])->first();

        if ($existing) {
            return $existing;
        }

        return Ingredient::create([
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'unit' => $line['unit'] ?? 'pc',
            'category' => $line['category'] ?? 'ingredient',
            'is_active' => true,
        ]);
    }

    /** Carry the price paid forward, keeping a trail when it moved. */
    private function applyPrice(
        Ingredient $ingredient,
        float $unitPrice,
        Carbon $purchasedOn,
        ?string $supplier,
        int $purchaseId,
        ?User $by,
    ): void {
        $previous = round((float) $ingredient->last_price, 2);

        if ($previous !== $unitPrice) {
            IngredientPriceChange::create([
                'ingredient_id' => $ingredient->id,
                'purchase_id' => $purchaseId,
                'old_price' => $previous,
                'new_price' => $unitPrice,
                'changed_on' => $purchasedOn,
                'changed_by' => $by?->id,
            ]);
        }

        $ingredient->update([
            'last_price' => $unitPrice,
            'last_purchased_on' => $purchasedOn,
            'supplier' => $ingredient->supplier ?: $supplier,
        ]);
    }

    private function summarise(array $names): string
    {
        $shown = array_slice($names, 0, 3);
        $rest = count($names) - count($shown);

        return implode(', ', $shown).($rest > 0 ? " +{$rest} more" : '');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'ingredient';
        $slug = $base;
        $i = 2;

        while (Ingredient::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
