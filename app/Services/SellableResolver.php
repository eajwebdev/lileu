<?php

namespace App\Services;

use App\Contracts\Sellable;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

/**
 * Turns request lines into the thing actually being sold.
 *
 * Every selling path — the counter, reseller orders, consignments — hands over
 * lines of {product_id, product_variant_id?} and gets back Sellables. The rule
 * that a product with flavours must be sold by flavour lives here once.
 */
class SellableResolver
{
    /**
     * @param  array<int, array{product_id:int|string, product_variant_id?:int|string|null}>  $lines
     * @param  bool  $lock  take a row lock, for paths that also move stock
     * @return Collection<string, Sellable> keyed by {@see self::key()}
     */
    public function resolve(array $lines, bool $lock = false): Collection
    {
        $productIds = collect($lines)->pluck('product_id')->map(fn ($id) => (int) $id)->filter()->unique();
        $variantIds = collect($lines)
            ->pluck('product_variant_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique();

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->when($lock, fn ($q) => $q->lockForUpdate())
            ->get()
            ->keyBy('id');

        $variants = ProductVariant::query()
            ->whereIn('id', $variantIds)
            ->when($lock, fn ($q) => $q->lockForUpdate())
            ->get()
            ->keyBy('id');

        // Eager-load parents so a variant can name its product without N+1.
        $variants->each(fn (ProductVariant $v) => $v->setRelation('product', $products->get($v->product_id)));

        // Knowing which products have flavours is what makes "pick one" enforceable.
        $withVariants = ProductVariant::query()
            ->whereIn('product_id', $productIds)
            ->where('is_active', true)
            ->pluck('product_id')
            ->unique()
            ->flip();

        $out = collect();

        foreach ($lines as $line) {
            $productId = (int) $line['product_id'];
            $variantId = (int) ($line['product_variant_id'] ?? 0) ?: null;
            $product = $products->get($productId);

            abort_unless($product, 422, 'One of the selected products is no longer available.');

            if ($variantId) {
                $variant = $variants->get($variantId);

                abort_unless($variant, 422, 'One of the selected flavours is no longer available.');
                abort_unless(
                    $variant->product_id === $productId,
                    422,
                    "{$variant->name} does not belong to {$product->name}.",
                );
                abort_unless($variant->is_active, 422, "{$variant->name} is no longer sold.");

                $out->put($this->key($productId, $variantId), $variant);

                continue;
            }

            abort_if(
                $withVariants->has($productId),
                422,
                "{$product->name} is sold by flavour — pick one before adding it.",
            );

            $out->put($this->key($productId, null), $product);
        }

        return $out;
    }

    /** The identity of a line: a product, or one of its flavours. */
    public function key(int $productId, ?int $variantId): string
    {
        return $productId.':'.($variantId ?: 0);
    }

    /** @param array{product_id:int|string, product_variant_id?:int|string|null} $line */
    public function keyForLine(array $line): string
    {
        return $this->key(
            (int) $line['product_id'],
            (int) ($line['product_variant_id'] ?? 0) ?: null,
        );
    }
}
