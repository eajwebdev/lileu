<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Reseller;
use App\Models\User;
use App\Services\ConsignmentService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\CreatesShopData;
use Tests\TestCase;

/**
 * Selling a product that comes in flavours.
 *
 * Price and stock live on the flavour, so every path has to name one — and
 * the stock that moves must be the flavour's, never the product's.
 */
class ProductVariantFlowTest extends TestCase
{
    use CreatesShopData;
    use RefreshDatabase;

    private Product $product;

    private ProductVariant $classic;

    private ProductVariant $matcha;

    private User $admin;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create(['name' => 'Graham', 'slug' => 'graham', 'accent' => 'caramel']);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'Graham Nest',
            'slug' => 'graham-nest',
            'sku' => 'GN-01',
            'min_reseller_qty' => 1,
            'is_active' => true,
            'is_available' => true,
            'available_to_resellers' => true,
        ]);

        $this->classic = ProductVariant::create([
            'product_id' => $this->product->id,
            'name' => 'Cloudy Classic',
            'slug' => 'cloudy-classic',
            'sku' => 'GN-CL',
            'retail_price' => 13,
            'reseller_price' => 10,
            'cost_price' => 6,
            'stock' => 20,
        ]);

        $this->matcha = ProductVariant::create([
            'product_id' => $this->product->id,
            'name' => 'Misty Green',
            'slug' => 'misty-green',
            'sku' => 'GN-MG',
            'retail_price' => 16,
            'reseller_price' => 12,
            'cost_price' => 7.5,
            'stock' => 5,
        ]);

        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
        $this->cashier = User::factory()->create(['role' => User::ROLE_CASHIER, 'is_active' => true]);
    }

    public function test_a_product_reports_the_range_and_total_of_its_flavours(): void
    {
        $product = $this->product->fresh('variants');

        $this->assertTrue($product->hasVariants());
        $this->assertSame(13.0, $product->lowestRetailPrice());
        $this->assertSame(16.0, $product->highestRetailPrice());
        $this->assertSame(25, $product->totalStock(), 'Stock is the sum of the flavours.');
    }

    public function test_the_counter_sells_a_flavour_and_takes_stock_from_that_flavour_only(): void
    {
        $this->actingAs($this->cashier)
            ->post(route('pos.sales.store'), [
                'items' => [
                    ['product_id' => $this->product->id, 'product_variant_id' => $this->matcha->id, 'quantity' => 2],
                ],
                'method' => 'cash',
                'amount_tendered' => 40,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(3, $this->matcha->refresh()->stock, 'Only the flavour sold loses stock.');
        $this->assertSame(20, $this->classic->refresh()->stock);

        $item = \App\Models\PosSaleItem::sole();
        $this->assertSame($this->matcha->id, $item->product_variant_id);
        $this->assertSame('Graham Nest', $item->product_name);
        $this->assertSame('Misty Green', $item->variant_name);
        $this->assertSame('16.00', $item->unit_price, 'The flavour sets the price.');
    }

    public function test_two_flavours_of_one_product_are_two_lines_on_the_same_sale(): void
    {
        $this->actingAs($this->cashier)
            ->post(route('pos.sales.store'), [
                'items' => [
                    ['product_id' => $this->product->id, 'product_variant_id' => $this->classic->id, 'quantity' => 2],
                    ['product_id' => $this->product->id, 'product_variant_id' => $this->matcha->id, 'quantity' => 1],
                ],
                'method' => 'cash',
                'amount_tendered' => 100,
            ])
            ->assertSessionHasNoErrors();

        $sale = \App\Models\PosSale::sole();

        $this->assertSame(2, $sale->items()->count());
        $this->assertSame('42.00', $sale->total, '2 x 13 + 1 x 16');
        $this->assertSame(18, $this->classic->refresh()->stock);
        $this->assertSame(4, $this->matcha->refresh()->stock);
    }

    public function test_the_same_flavour_twice_on_one_sale_is_rejected(): void
    {
        $this->actingAs($this->cashier)
            ->post(route('pos.sales.store'), [
                'items' => [
                    ['product_id' => $this->product->id, 'product_variant_id' => $this->classic->id, 'quantity' => 1],
                    ['product_id' => $this->product->id, 'product_variant_id' => $this->classic->id, 'quantity' => 1],
                ],
                'method' => 'cash',
                'amount_tendered' => 100,
            ])
            ->assertSessionHasErrors('items');

        $this->assertSame(20, $this->classic->refresh()->stock);
    }

    public function test_selling_a_flavoured_product_without_naming_a_flavour_is_refused(): void
    {
        try {
            $this->withoutExceptionHandling()
                ->actingAs($this->cashier)
                ->post(route('pos.sales.store'), [
                    'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
                    'method' => 'cash',
                    'amount_tendered' => 100,
                ]);

            $this->fail('Selling the parent product should have been refused.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertStringContainsString('sold by flavour', $e->getMessage());
        }

        $this->assertSame(20, $this->classic->refresh()->stock);
    }

    public function test_a_flavour_cannot_be_oversold(): void
    {
        $this->actingAs($this->cashier)
            ->post(route('pos.sales.store'), [
                'items' => [
                    ['product_id' => $this->product->id, 'product_variant_id' => $this->matcha->id, 'quantity' => 6],
                ],
                'method' => 'cash',
                'amount_tendered' => 200,
            ])
            ->assertSessionHasErrors('items');

        $this->assertSame(5, $this->matcha->refresh()->stock);
    }

    public function test_a_flavour_belonging_to_another_product_is_refused(): void
    {
        $other = Product::create([
            'category_id' => $this->product->category_id,
            'name' => 'Munchkin',
            'slug' => 'munchkin',
            'sku' => 'MK-01',
            'retail_price' => 10,
            'reseller_price' => 8,
            'stock' => 10,
            'min_reseller_qty' => 1,
        ]);

        try {
            $this->withoutExceptionHandling()
                ->actingAs($this->cashier)
                ->post(route('pos.sales.store'), [
                    'items' => [
                        // Someone else's flavour, pointed at this product.
                        ['product_id' => $other->id, 'product_variant_id' => $this->matcha->id, 'quantity' => 1],
                    ],
                    'method' => 'cash',
                    'amount_tendered' => 100,
                ]);

            $this->fail('A mismatched flavour should have been refused.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertStringContainsString('does not belong to', $e->getMessage());
        }
    }

    public function test_a_reseller_order_prices_each_flavour_on_its_own(): void
    {
        $seller = $this->approvedSeller();

        $order = app(OrderService::class)->placeResellerOrder(
            $seller,
            [
                ['product_id' => $this->product->id, 'product_variant_id' => $this->classic->id, 'quantity' => 10],
                ['product_id' => $this->product->id, 'product_variant_id' => $this->matcha->id, 'quantity' => 5],
            ],
            ['fulfillment_type' => 'pickup', 'date_needed' => now()->addDays(2)->toDateString()],
        );

        $this->assertSame(2, $order->items()->count());
        $this->assertSame('160.00', $order->subtotal, '10 x 10 + 5 x 12');

        $line = $order->items()->where('product_variant_id', $this->matcha->id)->sole();
        $this->assertSame('Misty Green', $line->variant_name);
        $this->assertSame('12.00', $line->unit_price);
    }

    public function test_a_consigned_flavour_returns_to_its_own_shelf(): void
    {
        $seller = $this->approvedSeller();
        $consignments = app(ConsignmentService::class);

        $consignment = $consignments->issue(
            $seller,
            [['product_id' => $this->product->id, 'product_variant_id' => $this->matcha->id, 'quantity' => 5]],
            ['issued_on' => now()->toDateString()],
            $this->admin,
        );

        $this->assertSame(0, $this->matcha->refresh()->stock, 'Issuing takes stock from the flavour.');
        $this->assertSame(20, $this->classic->refresh()->stock);

        $item = $consignment->items()->sole();
        $this->assertSame($this->matcha->id, $item->product_variant_id);

        $consignments->settle(
            $consignment,
            [['consignment_item_id' => $item->id, 'sold' => 3, 'returned' => 2]],
            ['is_final' => true],
            $this->admin,
        );

        // The two good returns go back to Misty Green, not to the product.
        $this->assertSame(2, $this->matcha->refresh()->stock);
        $this->assertSame(20, $this->classic->refresh()->stock);
    }

    public function test_an_unavailable_flavour_cannot_be_sold_while_its_siblings_can(): void
    {
        $this->matcha->update(['is_available' => false]);

        $this->actingAs($this->cashier)
            ->post(route('pos.sales.store'), [
                'items' => [
                    ['product_id' => $this->product->id, 'product_variant_id' => $this->matcha->id, 'quantity' => 1],
                ],
                'method' => 'cash',
                'amount_tendered' => 100,
            ])
            ->assertSessionHasErrors('items');

        $this->actingAs($this->cashier)
            ->post(route('pos.sales.store'), [
                'items' => [
                    ['product_id' => $this->product->id, 'product_variant_id' => $this->classic->id, 'quantity' => 1],
                ],
                'method' => 'cash',
                'amount_tendered' => 100,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(19, $this->classic->refresh()->stock);
    }

    public function test_admin_can_add_edit_and_remove_a_flavour(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.products.variants.store', $this->product), [
                'name' => 'Cocoa Cascade',
                'sku' => 'GN-CO',
                'retail_price' => 13,
                'reseller_price' => 10,
                'cost_price' => 6,
                'stock' => 30,
                'tracks_stock' => true,
                'low_stock_threshold' => 5,
                'is_active' => true,
                'is_available' => true,
            ])
            ->assertSessionHasNoErrors();

        $cocoa = ProductVariant::where('sku', 'GN-CO')->sole();
        $this->assertSame($this->product->id, $cocoa->product_id);
        $this->assertSame('cocoa-cascade', $cocoa->slug);

        $this->actingAs($this->admin)
            ->put(route('admin.products.variants.update', [$this->product, $cocoa]), [
                'name' => 'Cocoa Cascade',
                'sku' => 'GN-CO',
                'retail_price' => 15,
                'reseller_price' => 11,
                'stock' => 30,
                'tracks_stock' => true,
                'low_stock_threshold' => 5,
                'is_active' => true,
                'is_available' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('15.00', $cocoa->refresh()->retail_price);

        $this->actingAs($this->admin)
            ->delete(route('admin.products.variants.destroy', [$this->product, $cocoa]))
            ->assertSessionHasNoErrors();

        $this->assertNull(ProductVariant::find($cocoa->id));
    }

    public function test_a_flavour_cannot_be_moved_onto_a_product_it_does_not_belong_to(): void
    {
        $other = Product::create([
            'category_id' => $this->product->category_id,
            'name' => 'Munchkin',
            'slug' => 'munchkin',
            'sku' => 'MK-01',
            'retail_price' => 10,
            'reseller_price' => 8,
            'stock' => 10,
            'min_reseller_qty' => 1,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.products.variants.destroy', [$other, $this->matcha]))
            ->assertNotFound();

        $this->assertNotNull(ProductVariant::find($this->matcha->id));
    }

    public function test_removing_a_flavour_leaves_what_was_already_sold_intact(): void
    {
        $this->actingAs($this->cashier)->post(route('pos.sales.store'), [
            'items' => [
                ['product_id' => $this->product->id, 'product_variant_id' => $this->matcha->id, 'quantity' => 1],
            ],
            'method' => 'cash',
            'amount_tendered' => 20,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.products.variants.destroy', [$this->product, $this->matcha]))
            ->assertSessionHasNoErrors();

        $item = \App\Models\PosSaleItem::sole();

        $this->assertNull($item->product_variant_id);
        $this->assertSame('Misty Green', $item->variant_name, 'The receipt keeps its own name snapshot.');
        $this->assertSame('16.00', $item->unit_price);
    }

    public function test_a_product_without_flavours_still_sells_as_itself(): void
    {
        $simple = Product::create([
            'category_id' => $this->product->category_id,
            'name' => 'Munchkin Glazed',
            'slug' => 'munchkin-glazed',
            'sku' => 'MK-GL',
            'retail_price' => 10,
            'reseller_price' => 8,
            'stock' => 12,
            'min_reseller_qty' => 1,
        ]);

        $this->actingAs($this->cashier)
            ->post(route('pos.sales.store'), [
                'items' => [['product_id' => $simple->id, 'quantity' => 3]],
                'method' => 'cash',
                'amount_tendered' => 50,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(9, $simple->refresh()->stock);

        $item = \App\Models\PosSaleItem::sole();
        $this->assertNull($item->product_variant_id);
        $this->assertNull($item->variant_name);
        $this->assertSame('Munchkin Glazed', $item->product_name);
    }

    public function test_the_counter_lists_one_tile_per_flavour(): void
    {
        $this->actingAs($this->cashier)
            ->get(route('pos.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Pos/Terminal')
                ->has('products', 2)
                ->where('products.0.label', 'Graham Nest — Cloudy Classic')
                ->where('products.0.variant_id', $this->classic->id)
                ->where('products.1.label', 'Graham Nest — Misty Green'));
    }

    public function test_the_storefront_shows_the_flavours_under_the_product(): void
    {
        $this->get(route('products.show', $this->product))
            ->assertInertia(fn ($page) => $page
                ->component('Site/Product')
                ->where('product.has_variants', true)
                ->has('product.variants', 2)
                ->where('product.from_price', fn ($p) => (float) $p === 13.0)
                ->where('product.to_price', fn ($p) => (float) $p === 16.0));
    }

    public function test_cancelling_a_consignment_returns_stock_to_the_flavour(): void
    {
        $seller = $this->approvedSeller();
        $consignments = app(ConsignmentService::class);

        $consignment = $consignments->issue(
            $seller,
            [['product_id' => $this->product->id, 'product_variant_id' => $this->matcha->id, 'quantity' => 5]],
            ['issued_on' => now()->toDateString()],
            $this->admin,
        );

        $this->assertSame(0, $this->matcha->refresh()->stock);

        $consignments->cancel($consignment);

        // Everything outstanding goes back to Misty Green, not to the product.
        $this->assertSame(5, $this->matcha->refresh()->stock);
        $this->assertSame(20, $this->classic->refresh()->stock);
        $this->assertSame(0, (int) $this->product->refresh()->stock);
    }

    public function test_low_stock_counts_flavours_and_ignores_the_empty_parent(): void
    {
        // The parent holds no stock of its own; only Misty Green is genuinely low.
        $this->matcha->update(['stock' => 2, 'low_stock_threshold' => 5]);
        $this->classic->update(['stock' => 50, 'low_stock_threshold' => 5]);

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Dashboard')
                ->where('counters.low_stock', 1)
                ->has('lowStock', 1)
                ->where('lowStock.0.name', 'Graham Nest — Misty Green')
                ->where('lowStock.0.stock', 2));
    }

    public function test_a_receipt_line_names_the_flavour(): void
    {
        $seller = $this->approvedSeller();

        $order = app(OrderService::class)->placeResellerOrder(
            $seller,
            [['product_id' => $this->product->id, 'product_variant_id' => $this->matcha->id, 'quantity' => 3]],
            ['fulfillment_type' => 'pickup', 'date_needed' => now()->addDay()->toDateString()],
        );

        $payment = $this->payFor($order, $this->admin);

        $this->actingAs($this->admin)
            ->get(route('receipts.show', $payment))
            ->assertInertia(fn ($page) => $page
                ->component('Receipts/Show')
                ->where('data.items.0.name', 'Graham Nest — Misty Green'));

        // The printable version renders the same line as real HTML.
        $this->actingAs($this->admin)
            ->get(route('receipts.print', $payment))
            ->assertOk()
            ->assertSee('Misty Green', false);
    }

    public function test_the_counter_receipt_names_the_flavour(): void
    {
        $this->actingAs($this->cashier)->post(route('pos.sales.store'), [
            'items' => [
                ['product_id' => $this->product->id, 'product_variant_id' => $this->classic->id, 'quantity' => 1],
            ],
            'method' => 'cash',
            'amount_tendered' => 20,
        ]);

        $sale = \App\Models\PosSale::sole();

        $this->actingAs($this->cashier)
            ->get(route('pos.sales.receipt', $sale))
            ->assertOk()
            ->assertSee('Cloudy Classic', false);
    }

    public function test_the_admin_order_page_names_the_flavour(): void
    {
        $seller = $this->approvedSeller();

        $order = app(OrderService::class)->placeResellerOrder(
            $seller,
            [['product_id' => $this->product->id, 'product_variant_id' => $this->matcha->id, 'quantity' => 2]],
            ['fulfillment_type' => 'pickup', 'date_needed' => now()->addDay()->toDateString()],
        );

        $this->actingAs($this->admin)
            ->get(route('admin.orders.show', $order->order_number))
            ->assertInertia(fn ($page) => $page
                ->where('order.items.0.name', 'Graham Nest — Misty Green'));
    }

    public function test_the_seller_approval_list_quotes_the_cheapest_flavour(): void
    {
        $seller = $this->approvedSeller();

        $this->actingAs($this->admin)
            ->get(route('admin.resellers.show', $seller))
            ->assertInertia(fn ($page) => $page
                ->where('catalog.0.has_variants', true)
                // Not the product's own empty price.
                ->where('catalog.0.reseller_price', fn ($v) => (float) $v === 10.0)
                ->where('catalog.0.retail_price', fn ($v) => (float) $v === 13.0));
    }
}
