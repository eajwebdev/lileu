<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The menu: Graham Nest and its flavours.
 *
 * Graham Nest is the product; each flavour is a variant carrying its own price
 * and stock. Nothing sells at the product level once flavours exist — a
 * customer always picks one.
 *
 * Runnable on its own, so the menu can be re-seeded without touching branding,
 * staff, or anything the shop has traded:
 *
 *     php artisan db:seed --class=ProductSeeder --force
 *
 * Every write is an updateOrCreate keyed on SKU, so running it twice changes
 * nothing the second time, and it leaves other products alone.
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $product = Product::updateOrCreate(
            ['sku' => 'GN-01'],
            [
                'category_id' => $this->category()->id,
                'name' => 'Graham Nest',
                'slug' => 'graham-nest',
                'description' => 'Buttery graham layers under a flavour of your choosing.',
                'min_reseller_qty' => 10,
                'is_active' => true,
                'is_available' => true,
                'is_featured' => true,
                'available_to_resellers' => true,
                'sort_order' => 0,
            ],
        );

        $flavours = [
            ['Cloudy Classic', 'GN-CL', 13, 10, 6, 'Airy whipped cream over a soft biscuit floor.'],
            ['Misty Green', 'GN-MG', 16, 12, 7.5, 'Matcha cream settled over toasted graham.'],
            ['Cocoa Cascade', 'GN-CO', 13, 10, 6, 'Dark cocoa pudding with a cascading ganache top.'],
        ];

        foreach ($flavours as $i => [$name, $sku, $retail, $wholesale, $cost, $desc]) {
            $variant = ProductVariant::firstOrNew(['sku' => $sku]);

            // What the menu says, refreshed every run.
            $variant->fill([
                'product_id' => $product->id,
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => $desc,
                'retail_price' => $retail,
                'reseller_price' => $wholesale,
                'cost_price' => $cost,
                'tracks_stock' => true,
                'low_stock_threshold' => 20,
                'sort_order' => $i,
            ]);

            // What the shop has traded is the shop's, not the seeder's. Stock
            // and the availability switch are set once and never overwritten,
            // so re-running this on a live shop cannot invent inventory.
            if (! $variant->exists) {
                $variant->fill([
                    'stock' => 100,
                    'is_active' => true,
                    'is_available' => true,
                ]);
            }

            $variant->save();
        }
    }

    /**
     * The shelf Graham Nest sits on. Created only if it is missing, so an
     * existing category keeps whatever the owner has since renamed it to.
     */
    private function category(): Category
    {
        return Category::firstOrCreate(
            ['slug' => 'graham'],
            [
                'name' => 'Graham',
                'accent' => 'caramel',
                'description' => 'Graham nests and layered graham cups.',
                'sort_order' => 0,
                'is_active' => true,
            ],
        );
    }
}
