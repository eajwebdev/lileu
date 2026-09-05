<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_mark_a_product_unavailable_while_it_remains_visible_on_the_landing_page(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Desserts',
            'slug' => 'desserts',
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Mango Graham Cup',
            'slug' => 'mango-graham-cup',
            'sku' => 'MGC-01',
            'retail_price' => 15,
            'reseller_price' => 12,
            'cost_price' => 8,
            'stock' => 20,
            'min_reseller_qty' => 1,
            'is_active' => true,
            'is_available' => true,
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.products.availability', $product), [
            'is_available' => false,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertFalse($product->refresh()->is_available);

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->component('Site/Landing')
            ->has('featured', 1)
            ->where('featured.0.name', 'Mango Graham Cup')
            ->where('featured.0.is_available', false));
    }
}
