<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Reseller;
use App\Models\ResellerOrder;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\ReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The money rules from the brand spec: a downpayment must never read as PAID,
 * each payment gets its own receipt number, and reissuing a receipt must not
 * post a second transaction.
 */
class ResellerPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    private Reseller $reseller;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create(['name' => 'Graham', 'slug' => 'graham', 'accent' => 'caramel']);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'Graham Nest Classic',
            'slug' => 'graham-nest-classic',
            'sku' => 'GN-CL',
            'retail_price' => 12,
            'reseller_price' => 9,
            'cost_price' => 5.5,
            'stock' => 500,
            'min_reseller_qty' => 10,
        ]);

        $user = User::create([
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@example.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_RESELLER,
        ]);

        $this->reseller = Reseller::create([
            'user_id' => $user->id,
            'code' => 'RS-TEST-0001',
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@example.test',
            'phone' => '09171234567',
            'status' => Reseller::STATUS_APPROVED,
            'downpayment_percent' => 50,
        ]);
    }

    private function placeOrder(int $quantity = 100): ResellerOrder
    {
        return app(OrderService::class)->placeResellerOrder(
            $this->reseller,
            [['product_id' => $this->product->id, 'quantity' => $quantity]],
            ['fulfillment_type' => 'pickup', 'date_needed' => now()->addDays(3)->toDateString()],
        );
    }

    public function test_a_new_order_owes_everything_and_stamps_as_pending(): void
    {
        $order = $this->placeOrder();

        $this->assertSame('900.00', $order->total);
        $this->assertSame('450.00', $order->downpayment_required);
        $this->assertSame('900.00', $order->balance);
        $this->assertSame(ResellerOrder::PAY_UNPAID, $order->payment_status);

        $stamp = app(ReceiptService::class)->stampFor($order);
        $this->assertSame('PENDING PAYMENT', $stamp['label']);
    }

    public function test_a_fifty_percent_downpayment_never_stamps_as_fully_paid(): void
    {
        $order = $this->placeOrder();
        $payments = app(PaymentService::class);

        $downpayment = $payments->markPaid($payments->openPayment($order));

        $this->assertSame('450.00', $downpayment->amount);
        $this->assertSame(Payment::KIND_DOWNPAYMENT, $downpayment->kind);

        $order->refresh();
        $this->assertSame('450.00', $order->amount_paid);
        $this->assertSame('450.00', $order->balance);
        $this->assertSame(ResellerOrder::PAY_DOWNPAYMENT, $order->payment_status);

        // Money is still owed, so the stamp must say so.
        $stamp = app(ReceiptService::class)->stampFor($order, $downpayment);
        $this->assertSame('50% DOWNPAYMENT PAID', $stamp['label']);
        $this->assertNotSame('FULLY PAID', $stamp['label']);
    }

    public function test_paying_the_balance_issues_a_second_receipt_and_settles_the_order(): void
    {
        $order = $this->placeOrder();
        $payments = app(PaymentService::class);

        $downpayment = $payments->markPaid($payments->openPayment($order));
        $balance = $payments->markPaid($payments->openPayment($order->refresh()));

        $this->assertSame(Payment::KIND_BALANCE, $balance->kind);
        $this->assertNotSame($downpayment->receipt_number, $balance->receipt_number);

        $order->refresh();
        $this->assertSame('900.00', $order->amount_paid);
        $this->assertSame('0.00', $order->balance);
        $this->assertSame(ResellerOrder::PAY_FULL, $order->payment_status);
        $this->assertSame('FULLY PAID', app(ReceiptService::class)->stampFor($order)['label']);
    }

    public function test_paying_less_than_the_downpayment_reads_as_partially_paid(): void
    {
        $order = $this->placeOrder();

        app(PaymentService::class)->recordManualPayment($order, 100.0, 'cash', 'OR-1', null);

        $order->refresh();
        $this->assertSame(ResellerOrder::PAY_PARTIAL, $order->payment_status);
        $this->assertSame('PARTIALLY PAID', app(ReceiptService::class)->stampFor($order)['label']);
    }

    public function test_regenerating_a_receipt_does_not_post_a_second_transaction(): void
    {
        $order = $this->placeOrder();
        $payments = app(PaymentService::class);

        $payment = $payments->markPaid($payments->openPayment($order));
        $paidAt = $payment->paid_at;

        // Re-marking is a no-op: a receipt is only a view of an existing payment.
        $payments->markPaid($payment->refresh());
        $payments->markPaid($payment->refresh());

        $order->refresh();
        $this->assertSame(1, $order->payments()->where('status', Payment::STATUS_PAID)->count());
        $this->assertSame('450.00', $order->amount_paid);
        $this->assertEquals($paidAt->timestamp, $payment->refresh()->paid_at->timestamp);
    }

    public function test_reopening_the_payment_page_reuses_the_pending_receipt_number(): void
    {
        $order = $this->placeOrder();
        $payments = app(PaymentService::class);

        $first = $payments->openPayment($order);
        $second = $payments->openPayment($order->refresh());

        $this->assertSame($first->receipt_number, $second->receipt_number);
        $this->assertSame(1, $order->payments()->count());
    }

    public function test_voiding_a_payment_returns_the_balance_to_the_order(): void
    {
        $order = $this->placeOrder();
        $payments = app(PaymentService::class);

        $payment = $payments->markPaid($payments->openPayment($order));
        $payments->voidPayment($payment);

        $order->refresh();
        $this->assertSame('0.00', $order->amount_paid);
        $this->assertSame('900.00', $order->balance);
        $this->assertSame(ResellerOrder::PAY_UNPAID, $order->payment_status);
    }

    public function test_a_cancelled_order_stamps_as_cancelled(): void
    {
        $order = $this->placeOrder();
        $order->update(['status' => ResellerOrder::STATUS_CANCELLED]);

        app(OrderService::class)->syncTotals($order);

        $this->assertSame(ResellerOrder::PAY_VOID, $order->refresh()->payment_status);
        $this->assertSame('CANCELLED', app(ReceiptService::class)->stampFor($order)['label']);
    }

    public function test_receipt_numbers_are_unique_and_carry_the_current_year(): void
    {
        $order = $this->placeOrder();
        $payments = app(PaymentService::class);

        $first = $payments->markPaid($payments->openPayment($order));
        $second = $payments->markPaid($payments->openPayment($order->refresh()));

        $this->assertMatchesRegularExpression('/^LE-'.now()->format('Ymd').'-\d{6}$/', $first->receipt_number);
        $this->assertMatchesRegularExpression('/^RE-'.now()->format('Y').'-\d{6}$/', $order->order_number);
        $this->assertNotSame($first->receipt_number, $second->receipt_number);
    }

    public function test_the_receipt_payload_never_leaks_gateway_internals(): void
    {
        $order = $this->placeOrder();
        $payments = app(PaymentService::class);

        $payment = $payments->markPaid($payments->openPayment($order));
        $payment->forceFill([
            'qr_payload' => 'SECRET-QR-STRING',
            'meta' => ['client_key' => 'pi_secret_key'],
        ])->save();

        $encoded = json_encode(app(ReceiptService::class)->forPayment($payment->refresh()));

        $this->assertStringNotContainsString('SECRET-QR-STRING', $encoded);
        $this->assertStringNotContainsString('pi_secret_key', $encoded);
        $this->assertStringContainsString($payment->receipt_number, $encoded);
    }
}
