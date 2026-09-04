<?php

namespace Tests\Feature;

use App\Models\Consignment;
use App\Models\ConsignmentSettlement;
use App\Models\Payment;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\Reseller;
use App\Models\ResellerOrder;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesShopData;
use Tests\TestCase;

/**
 * Every page, against a fully seeded shop.
 *
 * The per-flow tests cover behaviour; this one only asks whether each module
 * still renders with real data behind it, so a broken prop or a renamed
 * relation surfaces here rather than in front of a cashier.
 */
class ModuleSmokeTest extends TestCase
{
    use CreatesShopData;
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    private User $reseller;

    private Reseller $seller;

    protected function setUp(): void
    {
        parent::setUp();

        // Branding, menu and staff come from the seeder; the trading history
        // these pages display is built here.
        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();
        $this->cashier = User::where('role', User::ROLE_CASHIER)->firstOrFail();

        $this->seller = $this->approvedSeller();
        $this->reseller = $this->seller->user;

        $order = $this->placeOrder($this->seller);
        $this->payFor($order, $this->admin);
        $this->issueConsignment($this->seller);
    }

    /** @param  array<string, string>  $pages  route name => expected Inertia component */
    private function assertPagesRender(User $as, array $pages): void
    {
        foreach ($pages as $name => $component) {
            [$route, $params] = is_array($component) ? $component : [$name, []];

            $response = $this->actingAs($as)->get(is_array($component) ? $route : route($name, $params));

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "{$name} returned {$response->getStatusCode()} instead of 200.",
            );
        }
    }

    public function test_the_public_storefront_renders(): void
    {
        $product = Product::where('is_active', true)->firstOrFail();

        $this->get(route('home'))->assertOk();
        $this->get(route('products.index'))->assertOk();
        $this->get(route('products.show', $product))->assertOk();
        $this->get(route('reseller.apply'))->assertOk();
        $this->get(route('reseller.apply.received'))->assertOk();
        $this->get(route('login'))->assertOk();
        $this->get(route('register'))->assertOk();
        $this->get(route('password.request'))->assertOk();
    }

    public function test_every_admin_module_renders(): void
    {
        $order = ResellerOrder::firstOrFail();
        $seller = $this->seller;
        $consignment = Consignment::firstOrFail();

        $this->assertPagesRender($this->admin, [
            'admin.dashboard' => 'Admin/Dashboard',
            'admin.products.index' => 'Admin/Products/Index',
            'admin.resellers.index' => 'Admin/Resellers/Index',
            'admin.orders.index' => 'Admin/Orders/Index',
            'admin.payments.index' => 'Admin/Payments/Index',
            'admin.consignments.index' => 'Admin/Consignments/Index',
            'admin.consignments.create' => 'Admin/Consignments/Create',
            'admin.messages.index' => 'Admin/Messages/Index',
            'admin.ledger.index' => 'Admin/Ledger/Index',
            'admin.ingredients.index' => 'Admin/Ingredients/Index',
            'admin.purchases.index' => 'Admin/Purchases/Index',
            'admin.purchases.create' => 'Admin/Purchases/Create',
            'admin.reports.index' => 'Admin/Reports/Index',
            'admin.users.index' => 'Admin/Users/Index',
            'admin.settings.edit' => 'Admin/Settings/Edit',
        ]);

        // Detail pages need a real record behind them.
        $this->actingAs($this->admin)->get(route('admin.orders.show', $order))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.resellers.show', $seller))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.consignments.show', $consignment))->assertOk();
    }

    public function test_the_point_of_sale_renders_for_a_cashier(): void
    {
        $this->actingAs($this->cashier)->get(route('pos.index'))->assertOk();
        $this->actingAs($this->cashier)->get(route('pos.sales.index'))->assertOk();

        if ($sale = PosSale::first()) {
            $this->actingAs($this->cashier)->get(route('pos.sales.receipt', $sale))->assertOk();
        }
    }

    public function test_the_reseller_portal_renders(): void
    {
        $this->assertPagesRender($this->reseller, [
            'portal.dashboard' => 'Portal/Dashboard',
            'portal.catalog' => 'Portal/Catalog',
            'portal.orders.index' => 'Portal/Orders/Index',
            'portal.orders.create' => 'Portal/Orders/Create',
            'portal.messages.index' => 'Portal/Messages/Index',
        ]);

        $order = ResellerOrder::where('reseller_id', $this->reseller->reseller->id)->first();

        if ($order) {
            $this->actingAs($this->reseller)->get(route('portal.orders.show', $order))->assertOk();
        }

        // An approved seller has no use for the status page and is sent onward.
        $this->actingAs($this->reseller)
            ->get(route('portal.status'))
            ->assertRedirect(route('portal.dashboard'));
    }

    public function test_a_pending_reseller_sees_the_status_page_and_nothing_else(): void
    {
        $pending = $this->pendingSeller()->user;

        $this->actingAs($pending)->get(route('portal.status'))->assertOk();

        // Approval is what opens the rest of the portal.
        $this->actingAs($pending)->get(route('portal.dashboard'))->assertRedirect(route('portal.status'));
        $this->actingAs($pending)->get(route('portal.catalog'))->assertRedirect(route('portal.status'));
        $this->actingAs($pending)->get(route('portal.orders.create'))->assertRedirect(route('portal.status'));
    }

    public function test_receipts_and_documents_render(): void
    {
        $payment = Payment::firstOrFail();
        $order = ResellerOrder::firstOrFail();
        $consignment = Consignment::firstOrFail();

        $this->actingAs($this->admin)->get(route('receipts.show', $payment))->assertOk();
        $this->actingAs($this->admin)->get(route('receipts.print', $payment))->assertOk();
        $this->actingAs($this->admin)->get(route('receipts.summary', $order))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.consignments.slip', $consignment))->assertOk();

        if ($settlement = ConsignmentSettlement::first()) {
            $this->actingAs($this->admin)
                ->get(route('admin.consignments.settlement.receipt', $settlement))
                ->assertOk();
        }
    }

    public function test_pdf_documents_generate(): void
    {
        $payment = Payment::firstOrFail();
        $order = ResellerOrder::firstOrFail();

        $receipt = $this->actingAs($this->admin)->get(route('receipts.pdf', $payment));
        $receipt->assertOk();
        $this->assertSame('application/pdf', $receipt->headers->get('content-type'));

        $summary = $this->actingAs($this->admin)->get(route('receipts.summary.pdf', $order));
        $summary->assertOk();
        $this->assertSame('application/pdf', $summary->headers->get('content-type'));
    }

    public function test_the_account_pages_render(): void
    {
        $this->actingAs($this->admin)->get(route('profile.edit'))->assertOk();
        $this->actingAs($this->admin)->get(route('dashboard'))->assertRedirect(route('admin.dashboard'));
        $this->actingAs($this->cashier)->get(route('dashboard'))->assertRedirect(route('pos.index'));
    }

    public function test_roles_cannot_reach_each_others_modules(): void
    {
        $this->actingAs($this->cashier)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($this->reseller)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($this->reseller)->get(route('pos.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('portal.dashboard'))->assertForbidden();
    }

    public function test_a_guest_is_sent_to_login_for_every_private_module(): void
    {
        foreach (['admin.dashboard', 'pos.index', 'portal.dashboard', 'profile.edit'] as $name) {
            $this->get(route($name))->assertRedirect(route('login'));
        }
    }
}
