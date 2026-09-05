<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The landing page is a window onto the product list the shop already keeps.
 *
 * Nothing here is written by hand: the shelves, the cards, the counts and the
 * pictures all come from Admin → Products, and a product sold by flavour has to
 * read as several things rather than one.
 */
class LandingCatalogTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create(['name' => 'Graham', 'slug' => 'graham', 'is_active' => true]);
    }

    private function product(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'category_id' => $this->category->id,
            'name' => 'Graham Nest',
            'slug' => 'graham-nest',
            'sku' => 'GN-01',
            'retail_price' => 20,
            'reseller_price' => 16,
            'min_reseller_qty' => 1,
            'is_active' => true,
        ], $attributes));
    }

    private function flavour(Product $product, string $name, array $attributes = []): ProductVariant
    {
        return ProductVariant::create(array_merge([
            'product_id' => $product->id,
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'sku' => 'GN-'.strtoupper(substr($name, 0, 2)),
            'retail_price' => 13,
            'reseller_price' => 10,
            'stock' => 20,
            'is_active' => true,
        ], $attributes));
    }

    public function test_the_headline_count_counts_flavours_not_product_rows(): void
    {
        $product = $this->product();
        $this->flavour($product, 'Cloudy Classic');
        $this->flavour($product, 'Misty Green');
        $this->flavour($product, 'Cocoa Cascade');

        // One product on the shelf, but three things a customer can pick.
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->where('stats.products', 1)
            ->where('stats.flavours', 3)
            ->where('categories.0.flavours_count', 3)
            ->where('categories.0.products_count', 1));
    }

    public function test_a_product_without_flavours_still_counts_as_one(): void
    {
        $this->product();

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->where('stats.flavours', 1)
            ->where('categories.0.flavours_count', 1));
    }

    public function test_only_active_flavours_are_counted(): void
    {
        $product = $this->product();
        $this->flavour($product, 'Cloudy Classic');
        $this->flavour($product, 'Retired Berry', ['is_active' => false]);

        // The retired flavour is neither counted nor given a card.
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->where('stats.flavours', 1)
            ->has('featured', 1)
            ->where('featured.0.variant_name', 'Cloudy Classic'));
    }

    public function test_a_product_whose_flavours_are_all_retired_leaves_the_page(): void
    {
        $product = $this->product();
        $this->flavour($product, 'Retired Berry', ['is_active' => false]);

        // Nothing left to sell, so nothing to put on a shelf either.
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->has('featured', 0)
            ->where('stats.flavours', 0)
            ->has('categories', 0));
    }

    public function test_a_shelf_with_nothing_on_it_is_not_offered(): void
    {
        Category::create(['name' => 'Seasonal', 'slug' => 'seasonal', 'is_active' => true]);

        $this->product();

        // Two categories exist; only the one carrying a product is browsable.
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->has('categories', 1)
            ->where('categories.0.name', 'Graham'));
    }

    public function test_each_flavour_carries_its_own_price(): void
    {
        $product = $this->product();
        $this->flavour($product, 'Cloudy Classic', ['retail_price' => 13]);
        $this->flavour($product, 'Cocoa Cascade', ['retail_price' => 31]);

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->has('featured', 2)
            ->where('featured', fn ($items) => collect($items)
                ->pluck('retail_price', 'variant_name')
                ->all() === ['Cloudy Classic' => 13, 'Cocoa Cascade' => 31]));
    }

    public function test_the_shelf_price_looks_past_the_three_products_it_previews(): void
    {
        // The shelf only shows three faces, but "from" has to mean the whole
        // shelf, so the cheapest product must count even when it is not shown.
        foreach (['Alpha', 'Bravo', 'Charlie'] as $i => $name) {
            $this->product([
                'name' => $name, 'slug' => strtolower($name), 'sku' => 'P-'.$i,
                'retail_price' => 50, 'is_featured' => true,
            ]);
        }

        $this->product(['name' => 'Delta', 'slug' => 'delta', 'sku' => 'P-9', 'retail_price' => 12]);

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->has('categories.0.preview', 3)
            ->where('categories.0.products_count', 4)
            ->where('categories.0.from_price', 12));
    }

    public function test_a_product_photographed_only_through_its_flavours_still_shows_a_picture(): void
    {
        $product = $this->product();
        $this->flavour($product, 'Cloudy Classic');
        $this->flavour($product, 'Cocoa Cascade', ['image_path' => 'products/cocoa.jpg']);

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            // The flavour that was photographed shows its own picture; the shelf
            // borrows it to stand for a product that has none.
            ->where('featured', fn ($items) => str_contains(
                (string) collect($items)->firstWhere('variant_name', 'Cocoa Cascade')['image_url'],
                'products/cocoa.jpg',
            ))
            ->where('categories.0.preview.0.image_url', fn ($url) => str_contains((string) $url, 'products/cocoa.jpg')));
    }

    public function test_a_photo_is_addressed_relative_to_whatever_host_serves_the_shop(): void
    {
        // APP_URL is routinely left at localhost while the shop runs on another
        // port; an absolute URL built from it points every photo at a server
        // that does not have the file.
        $product = $this->product(['image_path' => 'products/nest.jpg']);
        $this->flavour($product, 'Cloudy Classic');

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->where('featured.0.image_url', '/storage/products/nest.jpg'));
    }

    public function test_every_flavour_gets_a_card_of_its_own(): void
    {
        $product = $this->product();
        $this->flavour($product, 'Cloudy Classic', ['retail_price' => 13]);
        $this->flavour($product, 'Cocoa Cascade', ['retail_price' => 31, 'image_path' => 'products/cocoa.jpg']);
        $this->flavour($product, 'Misty Green', ['retail_price' => 18, 'stock' => 0]);

        // Three flavours, three cards — the same shape the menu uses, each one
        // naming the product it belongs to and linking back at it.
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->has('featured', 3)
            ->where('featured.0.name', 'Graham Nest')
            ->where('featured.0.slug', 'graham-nest')
            ->where('featured.0.variant_name', 'Cloudy Classic')
            ->where('featured.0.retail_price', 13)
            // A flavour that ran out keeps its card, told straight.
            ->where('featured', fn ($items) => collect($items)
                ->firstWhere('variant_name', 'Misty Green')['stock'] === 0));
    }

    public function test_wholesale_pricing_never_reaches_the_public_page(): void
    {
        $product = $this->product();
        $this->flavour($product, 'Cloudy Classic');

        // What a reseller pays is quoted in their own portal. It has no business
        // in the source of a page anyone can open.
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->missing('featured.0.reseller_price')
            ->missing('featured.0.cost_price')
            ->missing('featured.0.min_reseller_qty'));
    }

    public function test_a_typed_in_url_is_left_exactly_as_it_was_typed(): void
    {
        $this->product(['image_path' => 'https://example.test/nest.jpg']);

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->where('featured.0.image_url', 'https://example.test/nest.jpg'));
    }
}
