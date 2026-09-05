<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

/**
 * One shape for "things that can be put in a basket".
 *
 * The working screens — the counter, consignments, reseller ordering — care
 * about the exact item leaving the shelf, not the product it belongs to. So
 * they get a flat list where a product with flavours contributes one row per
 * flavour, and a product without them contributes itself.
 */
class Catalog
{
    /**
     * @param  iterable<Product>  $products  with 'variants' and 'category' loaded
     * @return array<int, array<string, mixed>>
     */
    public static function sellables(iterable $products): array
    {
        $rows = [];

        foreach ($products as $product) {
            $variants = $product->relationLoaded('variants')
                ? $product->variants->where('is_active', true)
                : $product->activeVariants()->get();

            if ($variants->isEmpty()) {
                $rows[] = self::row($product, null, $product);

                continue;
            }

            foreach ($variants as $variant) {
                $variant->setRelation('product', $product);
                $rows[] = self::row($product, $variant, $variant);
            }
        }

        return $rows;
    }

    /**
     * The nested shape, for the storefront: a product with its flavours
     * underneath, so a shopper picks the product first and the flavour second.
     *
     * Wholesale pricing is deliberately absent. This shape is rendered into a
     * public page, and what a reseller pays is between the shop and the
     * reseller — it belongs in the portal, not in the source of the menu.
     */
    public static function grouped(iterable $products): array
    {
        $rows = [];

        foreach ($products as $product) {
            $variants = $product->relationLoaded('variants')
                ? $product->variants->where('is_active', true)
                : $product->activeVariants()->get();

            // A flavour knows how to fall back to the product's photo, so tell
            // each one where it came from rather than letting it look the
            // product up again on its own.
            $variants->each(fn (ProductVariant $v) => $v->setRelation('product', $product));

            $rows[] = [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'sku' => $product->sku,
                'description' => $product->description,
                // A product photographed only through its flavours still gets a
                // picture on its card, instead of a blank plate.
                'image_url' => $product->image_url ?: $variants
                    ->first(fn (ProductVariant $v) => filled($v->image_path))?->image_url,
                'category' => $product->category?->only(['id', 'name', 'slug', 'accent']),
                'has_variants' => $variants->isNotEmpty(),
                // The headline price is the cheapest flavour; from/to lets a
                // card say "from ₱13" when the flavours are not all one price.
                'retail_price' => $product->lowestRetailPrice(),
                'from_price' => $product->lowestRetailPrice(),
                'to_price' => $product->highestRetailPrice(),
                'in_stock' => $product->isAvailable(),
                'manually_unavailable' => ! $product->isManuallyAvailable(),
                'made_to_order' => ! $variants->isEmpty()
                    ? $variants->every(fn (ProductVariant $v) => ! $v->tracksStock())
                    : ! $product->tracksStock(),
                'is_featured' => $product->is_featured,
                'variants' => $variants
                    ->map(fn (ProductVariant $v) => [
                        'id' => $v->id,
                        'name' => $v->name,
                        'sku' => $v->sku,
                        'description' => $v->description,
                        'image_url' => $v->image_url,
                        // image_url falls back to the product's photo, so say
                        // whether this flavour was actually photographed — a row
                        // of flavours should not repeat one picture five times.
                        'has_own_photo' => filled($v->image_path),
                        'retail_price' => (float) $v->retail_price,
                        'stock' => (int) $v->stock,
                        'tracks_stock' => $v->tracksStock(),
                        'is_available' => $v->isAvailable(),
                        'made_to_order' => ! $v->tracksStock(),
                    ])
                    ->values()
                    ->all(),
            ];
        }

        return $rows;
    }

    private static function row(Product $product, ?ProductVariant $variant, $sellable): array
    {
        return [
            // Identity of a basket line: the product, plus the flavour if any.
            'key' => $product->id.':'.($variant?->id ?: 0),
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'slug' => $product->slug,
            'name' => $product->name,
            'variant_name' => $variant?->name,
            'is_featured' => (bool) $product->is_featured,
            'label' => $sellable->sellableLabel(),
            'sku' => $sellable->sellableSku(),
            'description' => $variant?->description ?: $product->description,
            'image_url' => $variant?->image_url ?: $product->image_url,
            'retail_price' => $sellable->retailPrice(),
            'reseller_price' => $sellable->resellerPrice(),
            'cost_price' => $sellable->costPrice(),
            'stock' => $sellable->availableStock(),
            'tracks_stock' => $sellable->tracksStock(),
            'is_available' => $sellable->isManuallyAvailable() && $product->isManuallyAvailable(),
            'min_reseller_qty' => $product->min_reseller_qty,
            'category_id' => $product->category_id,
            'category' => $product->category?->name,
            'accent' => $product->category?->accent ?? 'blush',
        ];
    }

    /**
     * The flat shape with the trade prices taken back out, for the public menu.
     *
     * @param  iterable<Product>  $products
     * @return array<int, array<string, mixed>>
     */
    public static function menu(iterable $products): array
    {
        return collect(self::sellables($products))
            ->map(fn (array $row) => collect($row)
                ->except(['reseller_price', 'cost_price', 'min_reseller_qty'])
                ->all())
            ->all();
    }

    /** @return Collection<int, Product> active products with what the presenters need */
    public static function activeProducts(): Collection
    {
        return Product::active()
            ->with(['category:id,name,slug,accent', 'variants'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
