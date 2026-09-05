<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The public menu.
 *
 * A shopper buys a flavour, not a product, so the menu lists flavours: one card
 * each, under the category the shop filed them in.
 */
class CatalogMenuTest extends TestCase
{
    use RefreshDatabase;

    private Category $graham;

    private Product $nest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->graham = Category::create(['name' => 'Graham', 'slug' => 'graham', 'is_active' => true]);

        $this->nest = Product::create([
            'category_id' => $this->graham->id,
            'name' => 'Graham Nest',
            'slug' => 'graham-nest',
            'sku' => 'GN-01',
            'retail_price' => 20,
            'reseller_price' => 16,
            'min_reseller_qty' => 1,
            'is_active' => true,
        ]);

        foreach ([['Cloudy Classic', 13], ['Misty Green', 16], ['Cocoa Cascade', 31]] as [$name, $price]) {
            ProductVariant::create([
                'product_id' => $this->nest->id,
                'name' => $name,
                'slug' => str($name)->slug()->value(),
                'sku' => 'GN-'.strtoupper(substr($name, 0, 2)),
                'retail_price' => $price,
                'reseller_price' => $price - 3,
                'stock' => 20,
                'is_active' => true,
            ]);
        }
    }

    public function test_a_product_sold_by_flavour_puts_every_flavour_on_the_menu(): void
    {
        $this->get(route('products.index'))->assertInertia(fn (Assert $page) => $page
            ->component('Site/Catalog')
            ->has('items', 3)
            ->where('items.0.name', 'Graham Nest')
            ->where('items.0.variant_name', 'Cloudy Classic')
            ->where('items.0.retail_price', 13)
            // Each card links at the flavour it shows.
            ->where('items.0.slug', 'graham-nest')
            ->where('items.0.variant_id', fn ($id) => filled($id)));
    }

    public function test_a_product_without_flavours_is_still_one_card(): void
    {
        Product::create([
            'category_id' => $this->graham->id,
            'name' => 'Plain Cup',
            'slug' => 'plain-cup',
            'sku' => 'PC-01',
            'retail_price' => 9,
            'reseller_price' => 7,
            'min_reseller_qty' => 1,
            'is_active' => true,
        ]);

        $this->get(route('products.index'))->assertInertia(fn (Assert $page) => $page
            ->has('items', 4)
            ->where('items.3.name', 'Plain Cup')
            ->where('items.3.variant_name', null)
            ->where('items.3.variant_id', null));
    }

    public function test_choosing_a_category_shows_every_flavour_on_that_shelf(): void
    {
        $cookies = Category::create(['name' => 'Cookies', 'slug' => 'cookies', 'is_active' => true]);

        Product::create([
            'category_id' => $cookies->id,
            'name' => 'Crinkle',
            'slug' => 'crinkle',
            'sku' => 'CR-01',
            'retail_price' => 25,
            'reseller_price' => 20,
            'min_reseller_qty' => 1,
            'is_active' => true,
        ]);

        $this->get(route('products.index', ['category' => 'graham']))->assertInertia(fn (Assert $page) => $page
            ->has('items', 3)
            ->where('items.0.category', 'Graham'));

        $this->get(route('products.index', ['category' => 'cookies']))->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)
            ->where('items.0.name', 'Crinkle'));
    }

    public function test_searching_a_flavour_leaves_only_that_flavour(): void
    {
        $this->get(route('products.index', ['q' => 'cloudy']))->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)
            ->where('items.0.variant_name', 'Cloudy Classic'));
    }

    public function test_searching_the_product_keeps_all_of_its_flavours(): void
    {
        $this->get(route('products.index', ['q' => 'graham nest']))->assertInertia(fn (Assert $page) => $page
            ->has('items', 3));
    }

    public function test_a_retired_flavour_is_off_the_menu(): void
    {
        $this->nest->variants()->where('name', 'Misty Green')->update(['is_active' => false]);

        $this->get(route('products.index'))->assertInertia(fn (Assert $page) => $page
            ->has('items', 2)
            ->where('items', fn ($items) => collect($items)->doesntContain('variant_name', 'Misty Green')));
    }

    public function test_the_menu_quotes_retail_and_nothing_else(): void
    {
        $this->get(route('products.index'))->assertInertia(fn (Assert $page) => $page
            ->where('items.0.retail_price', 13)
            ->missing('items.0.reseller_price')
            ->missing('items.0.cost_price'));
    }

    public function test_a_product_page_keeps_wholesale_pricing_to_itself(): void
    {
        $this->get(route('products.show', $this->nest))->assertInertia(fn (Assert $page) => $page
            ->component('Site/Product')
            ->missing('product.reseller_price')
            ->missing('product.variants.0.reseller_price'));
    }

    public function test_a_sold_out_flavour_still_shows_with_its_stock_told_straight(): void
    {
        $this->nest->variants()->where('name', 'Cocoa Cascade')->update(['stock' => 0]);

        $this->get(route('products.index'))->assertInertia(fn (Assert $page) => $page
            ->has('items', 3)
            ->where('items', function ($items) {
                $cocoa = collect($items)->firstWhere('variant_name', 'Cocoa Cascade');

                return $cocoa !== null && $cocoa['stock'] === 0 && $cocoa['tracks_stock'] === true;
            }));
    }
}
