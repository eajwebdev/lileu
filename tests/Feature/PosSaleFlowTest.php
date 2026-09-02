<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosSaleFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->create([
            'role' => User::ROLE_CASHIER,
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Cups',
            'slug' => 'cups',
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'Classic Graham Cup',
            'slug' => 'classic-graham-cup',
            'sku' => 'POS-GC-01',
            'retail_price' => 15,
            'reseller_price' => 12,
            'cost_price' => 8,
            'stock' => 10,
            'min_reseller_qty' => 1,
        ]);
    }

    public function test_cashier_can_complete_a_sale_and_inventory_is_deducted_exactly(): void
    {
        $response = $this->actingAs($this->cashier)->post(route('pos.sales.store'), [
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 3,
            ]],
            'discount' => 5,
            'method' => 'cash',
            'amount_tendered' => 50,
        ]);

        $response->assertRedirect(route('pos.index'));
        $response->assertSessionHasNoErrors();

        $this->assertSame(7, $this->product->refresh()->stock);
        $this->assertDatabaseHas('pos_sales', [
            'cashier_id' => $this->cashier->id,
            'subtotal' => 45,
            'discount' => 5,
            'total' => 40,
            'amount_tendered' => 50,
            'change_due' => 10,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('pos_sale_items', [
            'product_id' => $this->product->id,
            'quantity' => 3,
            'unit_price' => 15,
            'line_total' => 45,
        ]);
    }

    public function test_sale_is_rejected_without_changing_stock_when_quantity_is_unavailable(): void
    {
        $response = $this->actingAs($this->cashier)
            ->from(route('pos.index'))
            ->post(route('pos.sales.store'), [
                'items' => [[
                    'product_id' => $this->product->id,
                    'quantity' => 11,
                ]],
                'discount' => 0,
                'method' => 'cash',
                'amount_tendered' => 200,
            ]);

        $response->assertRedirect(route('pos.index'));
        $response->assertSessionHasErrors('items');
        $this->assertSame(10, $this->product->refresh()->stock);
        $this->assertDatabaseCount('pos_sales', 0);
        $this->assertDatabaseCount('pos_sale_items', 0);
    }

    public function test_made_to_order_product_can_be_sold_at_zero_stock_without_changing_inventory(): void
    {
        $this->product->update([
            'tracks_stock' => false,
            'stock' => 0,
        ]);

        $response = $this->actingAs($this->cashier)->post(route('pos.sales.store'), [
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 25,
            ]],
            'discount' => 0,
            'method' => 'cash',
            'amount_tendered' => 375,
        ]);

        $response->assertRedirect(route('pos.index'));
        $response->assertSessionHasNoErrors();
        $this->assertSame(0, $this->product->refresh()->stock);
        $this->assertDatabaseHas('pos_sale_items', [
            'product_id' => $this->product->id,
            'quantity' => 25,
            'line_total' => 375,
        ]);
    }

    public function test_cash_sale_is_rejected_when_discount_or_cash_received_is_invalid(): void
    {
        $tooMuchDiscount = $this->actingAs($this->cashier)
            ->from(route('pos.index'))
            ->post(route('pos.sales.store'), [
                'items' => [[
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                ]],
                'discount' => 31,
                'method' => 'cash',
                'amount_tendered' => 100,
            ]);

        $tooMuchDiscount->assertSessionHasErrors('discount');

        $notEnoughCash = $this->actingAs($this->cashier)
            ->from(route('pos.index'))
            ->post(route('pos.sales.store'), [
                'items' => [[
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                ]],
                'discount' => 0,
                'method' => 'cash',
                'amount_tendered' => 20,
            ]);

        $notEnoughCash->assertSessionHasErrors('amount_tendered');
        $this->assertSame(10, $this->product->refresh()->stock);
        $this->assertDatabaseCount('pos_sales', 0);
    }
}
