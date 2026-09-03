<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IngredientPurchaseFlowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
    }

    public function test_an_ingredient_named_on_a_purchase_becomes_reusable_afterwards(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.purchases.store'), [
                'purchased_on' => now()->toDateString(),
                'supplier' => 'Graham House',
                'items' => [
                    ['name' => 'Graham crackers', 'unit' => 'pack', 'quantity' => 10, 'unit_price' => 95],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $ingredient = Ingredient::firstWhere('name', 'Graham crackers');

        $this->assertNotNull($ingredient, 'The purchase should file the ingredient for reuse.');
        $this->assertSame('pack', $ingredient->unit);
        $this->assertSame('95.00', $ingredient->last_price);
        $this->assertSame('Graham House', $ingredient->supplier);

        // Reusable means it now arrives in the picker with its price attached.
        $this->actingAs($admin)
            ->get(route('admin.purchases.create'))
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Purchases/Create')
                ->has('ingredients', 1)
                ->where('ingredients.0.name', 'Graham crackers')
                ->where('ingredients.0.unit', 'pack')
                ->where('ingredients.0.last_price', fn ($price) => (float) $price === 95.0));
    }

    public function test_the_ingredient_and_purchase_pages_render_with_what_was_bought(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.purchases.store'), [
            'purchased_on' => now()->toDateString(),
            'supplier' => 'Pack N Go',
            'items' => [
                ['name' => 'Cup lids', 'unit' => 'pack', 'quantity' => 4, 'unit_price' => 85],
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.ingredients.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Ingredients/Index')
                ->has('ingredients.data', 1)
                ->where('ingredients.data.0.name', 'Cup lids')
                ->where('ingredients.data.0.times_bought', 1));

        $this->actingAs($admin)
            ->get(route('admin.purchases.index', ['month' => now()->format('Y-m')]))
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Purchases/Index')
                ->has('purchases.data', 1)
                ->has('purchases.data.0.items', 1)
                ->where('purchases.data.0.supplier', 'Pack N Go')
                ->where('totals.count', 1));

        // The ledger reads the same purchases without owning the write path.
        $this->actingAs($admin)
            ->get(route('admin.ledger.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Ledger/Index')
                ->where('purchases.0.item_count', 1));
    }

    public function test_a_price_corrected_before_saving_updates_the_catalog_and_is_recorded(): void
    {
        $admin = $this->admin();

        $ingredient = Ingredient::create([
            'name' => 'Chocolate chips',
            'slug' => 'chocolate-chips',
            'unit' => 'kg',
            'last_price' => 340,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.purchases.store'), [
                'purchased_on' => now()->toDateString(),
                'items' => [
                    // The buyer raised the prefilled price before saving.
                    ['ingredient_id' => $ingredient->id, 'quantity' => 2, 'unit_price' => 355],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $ingredient->refresh();

        $this->assertSame('355.00', $ingredient->last_price, 'The corrected price becomes the new default.');
        $this->assertSame(now()->toDateString(), $ingredient->last_purchased_on->toDateString());

        $change = $ingredient->priceChanges()->sole();
        $this->assertSame('340.00', $change->old_price);
        $this->assertSame('355.00', $change->new_price);
        $this->assertSame($admin->id, $change->changed_by);
    }

    public function test_buying_at_the_same_price_records_no_price_change(): void
    {
        $admin = $this->admin();

        $ingredient = Ingredient::create([
            'name' => 'Cup lids',
            'slug' => 'cup-lids',
            'unit' => 'pack',
            'last_price' => 85,
        ]);

        $this->actingAs($admin)->post(route('admin.purchases.store'), [
            'purchased_on' => now()->toDateString(),
            'items' => [
                ['ingredient_id' => $ingredient->id, 'quantity' => 4, 'unit_price' => 85],
            ],
        ]);

        $this->assertSame(0, $ingredient->priceChanges()->count());
        $this->assertNotNull($ingredient->refresh()->last_purchased_on);
    }

    public function test_typing_a_name_that_already_exists_reuses_it_instead_of_duplicating(): void
    {
        $admin = $this->admin();

        Ingredient::create([
            'name' => 'White sugar',
            'slug' => 'white-sugar',
            'unit' => 'kg',
            'last_price' => 78,
        ]);

        $this->actingAs($admin)->post(route('admin.purchases.store'), [
            'purchased_on' => now()->toDateString(),
            'items' => [
                // Different casing and spacing, same ingredient.
                ['name' => '  white SUGAR ', 'unit' => 'kg', 'quantity' => 5, 'unit_price' => 80],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Ingredient::where('name', 'White sugar')->count());
        $this->assertSame(1, Ingredient::count());
        $this->assertSame('80.00', Ingredient::sole()->last_price);
    }

    public function test_the_purchase_total_is_the_sum_of_its_lines(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.purchases.store'), [
            'purchased_on' => now()->toDateString(),
            'items' => [
                ['name' => 'Butter', 'unit' => 'kg', 'quantity' => 1.5, 'unit_price' => 420],
                ['name' => 'Eggs', 'unit' => 'tray', 'quantity' => 2, 'unit_price' => 250.5],
            ],
        ])->assertSessionHasNoErrors();

        $purchase = Purchase::sole();

        $this->assertSame('1131.00', $purchase->amount);
        $this->assertSame(2, $purchase->items()->count());
        // With no description typed, the basket names the row for the ledger.
        $this->assertSame('Butter, Eggs', $purchase->description);
    }

    public function test_a_purchase_needs_at_least_one_line(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.purchases.store'), [
                'purchased_on' => now()->toDateString(),
                'items' => [],
            ])
            ->assertSessionHasErrors('items');

        $this->assertSame(0, Purchase::count());
    }

    public function test_removing_an_ingredient_leaves_past_purchase_lines_intact(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.purchases.store'), [
            'purchased_on' => now()->toDateString(),
            'items' => [
                ['name' => 'Ube halaya', 'unit' => 'kg', 'quantity' => 2, 'unit_price' => 320],
            ],
        ]);

        $ingredient = Ingredient::sole();

        $this->actingAs($admin)
            ->delete(route('admin.ingredients.destroy', $ingredient))
            ->assertSessionHasNoErrors();

        $item = Purchase::sole()->items()->sole();

        $this->assertNull($item->ingredient_id);
        $this->assertSame('Ube halaya', $item->name, 'The line keeps its own name snapshot.');
        $this->assertSame('640.00', Purchase::sole()->amount);
    }
}
