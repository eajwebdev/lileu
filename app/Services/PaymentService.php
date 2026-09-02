<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\ResellerOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        private NumberGenerator $numbers,
        private OrderService $orders,
    ) {}

    /**
     * Opens a pending payment for whatever the order owes next. Never posts money —
     * that only happens in markPaid(), so a reissued receipt cannot double-count.
     */
    public function openPayment(ResellerOrder $order, ?float $amount = null, string $method = 'qrph'): Payment
    {
        $due = $amount !== null ? round($amount, 2) : $order->amountDueNow();
        $due = min($due, (float) $order->balance);

        abort_if($due <= 0, 422, 'This order has no outstanding balance.');

        // Reuse an unexpired pending payment for the same amount instead of
        // burning a receipt number every time the page is refreshed.
        $existing = $order->payments()
            ->where('status', Payment::STATUS_PENDING)
            ->where('method', $method)
            ->whereRaw('ABS(amount - ?) < 0.01', [$due])
            ->latest()
            ->first();

        if ($existing) {
            return $existing;
        }

        return Payment::create([
            'receipt_number' => $this->numbers->receiptNumber(),
            'reseller_order_id' => $order->id,
            'kind' => $order->nextPaymentKind(),
            'method' => $method,
            'status' => Payment::STATUS_PENDING,
            'amount' => $due,
            'currency' => config('lileu.orders.currency'),
            'expires_at' => now()->addHours(24),
        ]);
    }

    public function markPaid(Payment $payment, array $gateway = [], ?User $recordedBy = null): Payment
    {
        return DB::transaction(function () use ($payment, $gateway, $recordedBy) {
            if ($payment->isPaid()) {
                return $payment;
            }

            $payment->update([
                'status' => Payment::STATUS_PAID,
                'paid_at' => now(),
                'reference' => $gateway['reference'] ?? $payment->reference ?? strtoupper(bin2hex(random_bytes(5))),
                'paymongo_payment_id' => $gateway['payment_id'] ?? $payment->paymongo_payment_id,
                'recorded_by' => $recordedBy?->id ?? $payment->recorded_by,
            ]);

            $order = $payment->order()->first();
            $this->orders->syncTotals($order);

            // First money in confirms the order for the kitchen.
            if ($order->status === ResellerOrder::STATUS_PENDING) {
                $order->update([
                    'status' => ResellerOrder::STATUS_CONFIRMED,
                    'confirmed_at' => now(),
                ]);
            }

            $order->reseller()->increment('lifetime_value', (float) $payment->amount);

            return $payment->refresh();
        });
    }

    /** Records a payment already collected off-platform (cash, bank transfer). */
    public function recordManualPayment(ResellerOrder $order, float $amount, string $method, ?string $reference, ?User $by): Payment
    {
        $payment = $this->openPayment($order, $amount, $method);

        return $this->markPaid($payment, ['reference' => $reference], $by);
    }

    public function voidPayment(Payment $payment): Payment
    {
        $payment->update(['status' => Payment::STATUS_VOID]);
        $this->orders->syncTotals($payment->order);

        return $payment->refresh();
    }
}
