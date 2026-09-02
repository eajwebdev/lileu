<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Consignment;
use App\Models\Product;
use App\Models\Reseller;
use App\Models\User;
use App\Services\ConsignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ConsignmentFlowTest extends TestCase
{
    use RefreshDatabase;

    private ConsignmentService $consignments;

    private Product $product;

    private Reseller $seller;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create([
            'name' => 'Desserts',
            'slug' => 'desserts',
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'Graham Cup',
            'slug' => 'graham-cup',
            'sku' => 'GC-TEST',
            'retail_price' => 12,
            'reseller_price' => 9,
            'cost_price' => 5,
            'stock' => 20,
            'min_reseller_qty' => 1,
        ]);

        $this->admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $this->seller = Reseller::create([
            'code' => 'RS-TEST-0001',
            'name' => 'Student Seller',
            'business_name' => 'School Canteen Table',
            'phone' => '09171234567',
            'status' => Reseller::STATUS_APPROVED,
            'engagement' => Reseller::ENGAGEMENT_CONSIGNMENT,
            'downpayment_percent' => 0,
        ]);

        $this->consignments = app(ConsignmentService::class);
    }

    public function test_it_rejects_more_stock_than_is_available_without_changing_inventory(): void
    {
        try {
            $this->issue(21);
            $this->fail('Issuing unavailable stock should have failed.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
            $this->assertStringContainsString('only 20 pcs are available', $exception->getMessage());
        }

        $this->assertSame(20, $this->product->refresh()->stock);
        $this->assertDatabaseCount('consignments', 0);
    }

    public function test_daily_collections_keep_the_batch_open_until_every_unit_is_accounted_for(): void
    {
        $consignment = $this->issue(10);
        $item = $consignment->items()->firstOrFail();

        $this->consignments->settle($consignment, [[
            'consignment_item_id' => $item->id,
            'sold' => 4,
            'returned' => 1,
        ]], [
            'settled_on' => now()->toDateString(),
            'method' => 'cash',
            'is_final' => false,
            'notes' => 'First daily collection.',
        ], $this->admin);

        $consignment->refresh();
        $this->assertSame(Consignment::STATUS_OPEN, $consignment->status);
        $this->assertSame(5, $consignment->outstandingQuantity());
        $this->assertSame(11, $this->product->refresh()->stock);

        $this->consignments->settle($consignment, [[
            'consignment_item_id' => $item->id,
            'sold' => 2,
            'returned' => 2,
            'expired' => 1,
        ]], [
            'settled_on' => now()->addDay()->toDateString(),
            'method' => 'cash',
            'is_final' => true,
            'notes' => 'Final pickup; one cup expired.',
        ], $this->admin);

        $consignment->refresh();
        $this->assertSame(Consignment::STATUS_SETTLED, $consignment->status);
        $this->assertSame(6, $consignment->quantity_sold);
        $this->assertSame(3, $consignment->quantity_returned);
        $this->assertSame(1, $consignment->quantity_expired);
        $this->assertSame(0, $consignment->outstandingQuantity());
        $this->assertSame('54.00', $consignment->sold_value);
        $this->assertSame('54.00', $consignment->amount_collected);
        $this->assertSame('0.00', $consignment->amount_due);
        $this->assertSame('5.00', $consignment->loss_value);
        $this->assertSame(13, $this->product->refresh()->stock);
        $this->assertCount(2, $consignment->settlements);
    }

    public function test_made_to_order_product_can_be_consigned_and_returned_without_changing_inventory(): void
    {
        $this->product->update([
            'tracks_stock' => false,
            'stock' => 0,
        ]);

        $consignment = $this->issue(25);
        $item = $consignment->items()->firstOrFail();

        $this->assertFalse($item->tracks_stock);
        $this->assertSame(0, $this->product->refresh()->stock);

        $this->consignments->settle($consignment, [[
            'consignment_item_id' => $item->id,
            'sold' => 5,
            'returned' => 15,
            'expired' => 3,
            'damaged' => 2,
        ]], [
            'settled_on' => now()->toDateString(),
            'method' => 'cash',
            'is_final' => true,
        ], $this->admin);

        $consignment->refresh();
        $this->assertSame(Consignment::STATUS_SETTLED, $consignment->status);
        $this->assertSame(0, $consignment->outstandingQuantity());
        $this->assertSame(15, $consignment->quantity_returned);
        $this->assertSame(0, $this->product->refresh()->stock);
    }

    public function test_a_final_collection_cannot_hide_units_that_are_still_out(): void
    {
        $consignment = $this->issue(10);
        $item = $consignment->items()->firstOrFail();

        try {
            $this->consignments->settle($consignment, [[
                'consignment_item_id' => $item->id,
                'sold' => 4,
                'returned' => 3,
            ]], [
                'settled_on' => now()->toDateString(),
                'method' => 'cash',
                'is_final' => true,
            ], $this->admin);
            $this->fail('A final collection with outstanding units should have failed.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
            $this->assertStringContainsString('3 unaccounted units', $exception->getMessage());
        }

        $consignment->refresh();
        $this->assertSame(Consignment::STATUS_OPEN, $consignment->status);
        $this->assertSame(10, $consignment->outstandingQuantity());
        $this->assertDatabaseCount('consignment_settlements', 0);
        $this->assertSame(10, $this->product->refresh()->stock);
    }

    private function issue(int $quantity): Consignment
    {
        return $this->consignments->issue($this->seller, [[
            'product_id' => $this->product->id,
            'quantity' => $quantity,
        ]], [
            'issued_on' => now()->toDateString(),
            'due_on' => now()->addDay()->toDateString(),
            'notes' => 'Collect sales tomorrow and return unsold stock.',
        ], $this->admin);
    }
}
