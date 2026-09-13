<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Expense;
use App\Models\Message;
use App\Models\Product;
use App\Models\Reseller;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesShopData;
use Tests\TestCase;

/**
 * The admin write paths: the everyday editing that keeps the shop's own data
 * current. The flow tests cover selling; this covers running the place.
 */
class AdminManagementTest extends TestCase
{
    use CreatesShopData;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();
    }

    public function test_products_can_be_created_edited_and_removed(): void
    {
        $category = Category::firstOrFail();

        $payload = [
            'name' => 'Leche Flan Cup',
            'sku' => 'LF-01',
            'category_id' => $category->id,
            'description' => 'Steamed custard in a cup.',
            'retail_price' => 25,
            'reseller_price' => 19,
            'cost_price' => 11,
            'stock' => 40,
            'tracks_stock' => true,
            'low_stock_threshold' => 10,
            'min_reseller_qty' => 6,
            'is_active' => true,
            'is_available' => true,
            'is_featured' => false,
            'available_to_resellers' => true,
        ];

        $this->actingAs($this->admin)
            ->post(route('admin.products.store'), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $product = Product::where('sku', 'LF-01')->sole();
        $this->assertSame('leche-flan-cup', $product->slug);

        $this->actingAs($this->admin)
            ->put(route('admin.products.update', $product), [...$payload, 'retail_price' => 28])
            ->assertSessionHasNoErrors();

        $this->assertSame('28.00', $product->refresh()->retail_price);

        $this->actingAs($this->admin)
            ->delete(route('admin.products.destroy', $product))
            ->assertSessionHasNoErrors();

        $this->assertNull(Product::find($product->id));
    }

    public function test_a_duplicate_sku_is_rejected(): void
    {
        $existing = Product::firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('admin.products.store'), [
                'name' => 'Something else',
                'sku' => $existing->sku,
                'retail_price' => 10,
                'reseller_price' => 8,
                'stock' => 1,
                'tracks_stock' => true,
                'low_stock_threshold' => 1,
                'min_reseller_qty' => 1,
                'is_available' => true,
            ])
            ->assertSessionHasErrors('sku');
    }

    public function test_categories_can_be_managed(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.categories.store'), [
                'name' => 'Frozen',
                'description' => 'Straight from the freezer.',
                'accent' => 'blush',
            ])
            ->assertSessionHasNoErrors();

        $category = Category::where('name', 'Frozen')->sole();

        $this->actingAs($this->admin)
            ->put(route('admin.categories.update', $category), [
                'name' => 'Frozen Treats',
                'accent' => 'cherry',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Frozen Treats', $category->refresh()->name);

        $this->actingAs($this->admin)
            ->delete(route('admin.categories.destroy', $category))
            ->assertSessionHasNoErrors();

        $this->assertNull(Category::find($category->id));
    }

    public function test_staff_accounts_can_be_created_and_updated(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), [
                'name' => 'Second Cashier',
                'email' => 'cashier2@lileu.test',
                'phone' => '0917 000 0009',
                'role' => User::ROLE_CASHIER,
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertSessionHasNoErrors();

        $user = User::where('email', 'cashier2@lileu.test')->sole();
        $this->assertSame(User::ROLE_CASHIER, $user->role);
        $this->assertTrue(Hash::check('password123', $user->password));

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $user), [
                'name' => 'Second Cashier',
                'email' => 'cashier2@lileu.test',
                'role' => User::ROLE_CASHIER,
                'is_active' => false,
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse($user->refresh()->is_active);
    }

    public function test_a_deactivated_account_cannot_log_back_in(): void
    {
        $user = User::factory()->create([
            'email' => 'former@lileu.test',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_CASHIER,
            'is_active' => false,
        ]);

        // Correct credentials, but the account is closed.
        $this->post(route('login'), [
            'email' => 'former@lileu.test',
            'password' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        // Not even the pages gated by nothing more than 'auth'.
        $this->get(route('profile.edit'))->assertRedirect(route('login'));

        // The same account works again once it is switched back on.
        $user->update(['is_active' => true]);

        $this->post(route('login'), [
            'email' => 'former@lileu.test',
            'password' => 'password123',
        ])->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_seller_can_be_added_with_a_login_and_have_its_status_changed(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.resellers.store'), [
                'name' => 'Bea Cruz',
                'business_name' => 'Bea Sweets',
                'email' => 'bea@lileu.test',
                'phone' => '0917 111 2222',
                'engagement' => 'both',
                'discount_percent' => 10,
                'create_login' => true,
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $seller = Reseller::where('email', 'bea@lileu.test')->sole();

        // Added by us, so approved on the spot with a usable login.
        $this->assertSame(Reseller::STATUS_APPROVED, $seller->status);
        $this->assertNotNull($seller->user_id);
        $this->assertSame(User::ROLE_RESELLER, $seller->user->role);
        $this->assertNotNull($seller->code);

        $this->actingAs($this->admin)
            ->post(route('admin.resellers.status', $seller), [
                'status' => Reseller::STATUS_SUSPENDED,
                'admin_notes' => 'On hold pending a settlement.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(Reseller::STATUS_SUSPENDED, $seller->refresh()->status);

        // Suspension closes the portal behind them.
        $this->actingAs($seller->user)
            ->get(route('portal.dashboard'))
            ->assertRedirect(route('portal.status'));
    }

    public function test_the_products_a_seller_may_order_can_be_synced(): void
    {
        $seller = $this->approvedSeller();

        // Approval is granted per product, so the seller gets every flavour of
        // whichever products are on their list.
        Product::create([
            'category_id' => Category::firstOrFail()->id,
            'name' => 'Munchkin Glazed',
            'slug' => 'munchkin-glazed',
            'sku' => 'MK-GL',
            'retail_price' => 10,
            'reseller_price' => 7.5,
            'stock' => 50,
            'min_reseller_qty' => 1,
        ]);

        $ids = Product::query()->limit(2)->pluck('id')->all();

        $this->actingAs($this->admin)
            ->post(route('admin.resellers.products', $seller), [
                'products' => [
                    ['product_id' => $ids[0], 'custom_price' => 8.5, 'is_approved' => true],
                    ['product_id' => $ids[1], 'custom_price' => '', 'is_approved' => true],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing($ids, $seller->products()->pluck('products.id')->all());

        // A blank custom price falls back to the standard rate.
        $this->assertSame('8.50', $seller->products()->find($ids[0])->pivot->custom_price);
        $this->assertNull($seller->products()->find($ids[1])->pivot->custom_price);

        // Syncing replaces the list rather than adding to it.
        $this->actingAs($this->admin)
            ->post(route('admin.resellers.products', $seller), ['products' => []])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $seller->products()->count());
    }

    public function test_branding_settings_save_and_reach_the_storefront(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.update'), [
                'business_name' => "Lil'Eu Sweets & Co",
                'business_tagline' => 'Est. 2026',
                'business_email' => 'hello@lileu.test',
                'receipt_prefix' => 'LE',
                'order_prefix' => 'RE',
                'receipt_paper' => 'a4',
                'receipt_footer' => 'Salamat!',
                'default_downpayment_percent' => 40,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame("Lil'Eu Sweets & Co", Settings::get('business_name'));
        $this->assertSame(40, (int) Settings::get('default_downpayment_percent'));

        $this->get(route('home'))->assertOk()->assertSee("Lil'Eu Sweets & Co");
    }

    public function test_an_invalid_receipt_prefix_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.update'), [
                'business_name' => "Lil'Eu",
                'receipt_prefix' => 'le-1',   // lowercase and a dash
                'order_prefix' => 'RE',
                'receipt_paper' => 'a4',
                'default_downpayment_percent' => 50,
            ])
            ->assertSessionHasErrors('receipt_prefix');
    }

    public function test_admin_and_seller_can_message_each_other(): void
    {
        $seller = $this->approvedSeller();

        $this->actingAs($this->admin)
            ->post(route('admin.messages.store', $seller), ['body' => 'Your order is ready for pickup.'])
            ->assertSessionHasNoErrors();

        $this->actingAs($seller->user)
            ->post(route('portal.messages.store'), ['body' => 'Thank you, picking up at 3pm.'])
            ->assertSessionHasNoErrors();

        $bodies = Message::where('reseller_id', $seller->id)->pluck('body');

        $this->assertTrue($bodies->contains('Your order is ready for pickup.'));
        $this->assertTrue($bodies->contains('Thank you, picking up at 3pm.'));
    }

    public function test_overhead_expenses_can_be_recorded_and_removed(): void
    {
        $before = Expense::count();

        $this->actingAs($this->admin)
            ->post(route('admin.expenses.store'), [
                'incurred_on' => now()->toDateString(),
                'category' => 'utilities',
                'description' => 'Water bill',
                'amount' => 480.50,
            ])
            ->assertSessionHasNoErrors();

        $expense = Expense::where('description', 'Water bill')->sole();
        $this->assertSame('480.50', $expense->amount);
        $this->assertSame($this->admin->id, $expense->recorded_by);

        $this->actingAs($this->admin)
            ->delete(route('admin.expenses.destroy', $expense))
            ->assertSessionHasNoErrors();

        $this->assertSame($before, Expense::count());
    }

    public function test_a_public_reseller_application_lands_as_pending(): void
    {
        $this->post(route('reseller.apply.store'), [
            'name' => 'Walk-in Applicant',
            'email' => 'walkin@example.test',
            'phone' => '0917 555 4444',
            'business_name' => 'Walk-in Sweets',
            'city' => 'Mabinay',
            'address' => 'Poblacion',
            'engagement' => 'reseller',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasNoErrors();

        $applicant = Reseller::where('email', 'walkin@example.test')->sole();

        // Applications are reviewed, never auto-approved.
        $this->assertSame(Reseller::STATUS_PENDING, $applicant->status);
    }

    public function test_a_seller_can_be_edited_from_the_list(): void
    {
        $seller = $this->approvedSeller();

        // The list carries everything the edit form needs to open filled in —
        // the same fields adding one asks for, name and email included.
        $this->actingAs($this->admin)
            ->get(route('admin.resellers.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Resellers/Index')
                ->has('resellers.data.0.name')
                ->has('resellers.data.0.email')
                ->has('resellers.data.0.address')
                ->has('resellers.data.0.downpayment_percent')
                ->has('resellers.data.0.admin_notes'));

        $this->actingAs($this->admin)
            ->put(route('admin.resellers.update', $seller), [
                'name' => 'Juan Dela Cruz',
                'business_name' => 'Juan Sweets & Co',
                'email' => 'juan@example.test',
                'phone' => '09170000123',
                'city' => 'Mabinay',
                'address' => 'Poblacion, Mabinay',
                'engagement' => 'consignment',
                'discount_percent' => 12,
                'downpayment_percent' => 25,
                'admin_notes' => 'Pays on time.',
            ])
            ->assertSessionHasNoErrors();

        $seller->refresh();

        $this->assertSame('Juan Sweets & Co', $seller->business_name);
        $this->assertSame('consignment', $seller->engagement);
        $this->assertSame(12, $seller->discount_percent);
        $this->assertSame(25, $seller->downpayment_percent);
        $this->assertSame('Pays on time.', $seller->admin_notes);

        // A business name is added alongside the person, never over them.
        $this->assertSame('Juan Dela Cruz', $seller->name);
        $this->assertSame('Juan Dela Cruz', $seller->personName());
    }

    public function test_renaming_a_seller_renames_their_portal_login(): void
    {
        $seller = $this->approvedSeller();

        $this->actingAs($this->admin)
            ->put(route('admin.resellers.update', $seller), [
                'name' => 'Juana Dela Cruz',
                'email' => 'juana@example.test',
                'phone' => '09170000123',
                'engagement' => 'consignment',
            ])
            ->assertSessionHasNoErrors();

        $seller->refresh();

        $this->assertSame('Juana Dela Cruz', $seller->name);
        $this->assertSame('Juana Dela Cruz', $seller->user->name);
        $this->assertSame('juana@example.test', $seller->user->email);

        // Consignment sellers are never shown the wholesale terms, so an
        // absent value leaves what they already had alone.
        $this->assertSame(50, $seller->downpayment_percent);
    }

    public function test_a_seller_cannot_take_over_another_login_email(): void
    {
        $seller = $this->approvedSeller();

        $this->actingAs($this->admin)
            ->put(route('admin.resellers.update', $seller), [
                'name' => 'Juan Dela Cruz',
                'email' => $this->admin->email,
                'phone' => '09170000123',
                'engagement' => 'consignment',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_editing_a_seller_cannot_set_an_impossible_discount(): void
    {
        $seller = $this->approvedSeller();

        $this->actingAs($this->admin)
            ->put(route('admin.resellers.update', $seller), [
                'name' => 'Juan Dela Cruz',
                'email' => 'juan@example.test',
                'phone' => '09170000123',
                'engagement' => 'both',
                'discount_percent' => 80,      // capped at 50
                'downpayment_percent' => 150,  // capped at 100
            ])
            ->assertSessionHasErrors(['discount_percent', 'downpayment_percent']);

        $this->assertSame(0, $seller->refresh()->discount_percent);
    }

    public function test_editing_a_seller_leaves_their_status_alone(): void
    {
        $seller = $this->approvedSeller();

        $this->actingAs($this->admin)
            ->put(route('admin.resellers.update', $seller), [
                'name' => 'Juan Dela Cruz',
                'email' => 'juan@example.test',
                'phone' => '09170000123',
                'engagement' => 'both',
                'discount_percent' => 5,
                'downpayment_percent' => 50,
            ])
            ->assertSessionHasNoErrors();

        // Status changes go through the approval flow, not the edit form.
        $this->assertSame(Reseller::STATUS_APPROVED, $seller->refresh()->status);
    }
}
