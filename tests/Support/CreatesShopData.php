<?php

namespace Tests\Support;

use App\Models\Consignment;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Reseller;
use App\Models\ResellerOrder;
use App\Models\User;
use App\Services\ConsignmentService;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Hash;

/**
 * Trading records for tests that need a shop with history behind it.
 *
 * The seeder only lays down branding, the menu and staff, so anything that
 * has to have been sold, consigned or paid for is built here — through the
 * real services, so the records look exactly like the app's own.
 */
trait CreatesShopData
{
    protected function approvedSeller(array $attributes = []): Reseller
    {
        $user = User::factory()->create([
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@example.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_RESELLER,
            'is_active' => true,
        ]);

        return Reseller::create([
            'user_id' => $user->id,
            'code' => 'RS-TEST-0001',
            'name' => 'Juan Dela Cruz',
            'business_name' => 'Juan Sweets',
            'email' => 'juan@example.test',
            'phone' => '09171234567',
            'status' => Reseller::STATUS_APPROVED,
            'engagement' => Reseller::ENGAGEMENT_BOTH,
            'downpayment_percent' => 50,
            'applied_at' => now(),
            'approved_at' => now(),
        ] + $attributes);
    }

    protected function pendingSeller(): Reseller
    {
        $user = User::factory()->create([
            'name' => 'Ana Reyes',
            'email' => 'ana@example.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_RESELLER,
            'is_active' => true,
        ]);

        return Reseller::create([
            'user_id' => $user->id,
            'code' => 'RS-TEST-0002',
            'name' => 'Ana Reyes',
            'email' => 'ana@example.test',
            'phone' => '09179876543',
            'status' => Reseller::STATUS_PENDING,
            'engagement' => Reseller::ENGAGEMENT_RESELLER,
            'applied_at' => now(),
        ]);
    }

    /**
     * A basket line for a product, naming a flavour when the product has any —
     * the same choice a real order has to make.
     */
    protected function line(?Product $product, int $quantity): array
    {
        $product ??= Product::firstOrFail();
        $variant = $product->activeVariants()->first();

        return [
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'quantity' => $quantity,
        ];
    }

    protected function placeOrder(Reseller $seller, ?Product $product = null, int $quantity = 20): ResellerOrder
    {
        return app(OrderService::class)->placeResellerOrder(
            $seller,
            [$this->line($product, $quantity)],
            ['fulfillment_type' => 'pickup', 'date_needed' => now()->addDays(3)->toDateString()],
        );
    }

    /** An order with a settled receipt behind it, for the receipt pages. */
    protected function payFor(ResellerOrder $order, ?User $by = null): Payment
    {
        $payments = app(PaymentService::class);

        return $payments->markPaid($payments->openPayment($order), [], $by);
    }

    protected function issueConsignment(Reseller $seller, ?Product $product = null, int $quantity = 10): Consignment
    {
        return app(ConsignmentService::class)->issue(
            $seller,
            [$this->line($product, $quantity)],
            ['issued_on' => now()->toDateString()],
        );
    }
}
