<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\ResellerOrder;
use App\Support\Settings;
use Illuminate\Support\Carbon;

/**
 * Builds the single receipt payload shared by the web receipt, the print sheet
 * and the PDF, so all three are guaranteed to look the same.
 */
class ReceiptService
{
    public function forPayment(Payment $payment): array
    {
        $payment->loadMissing(['order.items', 'order.reseller']);
        $order = $payment->order;

        return array_merge($this->forOrder($order), [
            'receipt' => [
                'number' => $payment->receipt_number,
                'kind' => $payment->kind,
                'kind_label' => $payment->kindLabel(),
                'method' => $payment->method,
                'method_label' => $payment->methodLabel(),
                'status' => $payment->status,
                'amount' => (float) $payment->amount,
                'reference' => $payment->reference,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'paid_date' => $payment->paid_at?->format('F j, Y'),
                'paid_time' => $payment->paid_at?->format('g:i A'),
                'issued_date' => $payment->created_at->format('F j, Y'),
            ],
            'stamp' => $this->stampFor($order, $payment),
        ]);
    }

    /** The consolidated order payment summary: every payment on one sheet. */
    public function forOrder(ResellerOrder $order): array
    {
        $order->loadMissing(['items', 'reseller', 'payments']);

        $payments = $order->payments
            ->where('status', Payment::STATUS_PAID)
            ->sortBy('paid_at')
            ->values()
            ->map(fn (Payment $p) => [
                'receipt_number' => $p->receipt_number,
                'date' => $p->paid_at?->format('M j, Y'),
                'time' => $p->paid_at?->format('g:i A'),
                'method_label' => $p->methodLabel(),
                'kind_label' => $p->kindLabel(),
                'reference' => $p->reference,
                'amount' => (float) $p->amount,
            ])->all();

        return [
            'brand' => Settings::brand(),
            'options' => Settings::receipt(),
            'order' => [
                'id' => $order->id,
                'number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'fulfillment_type' => $order->fulfillment_type,
                'delivery_address' => $order->fulfillment_type === 'delivery' ? $order->delivery_address : null,
                'ordered_date' => $order->created_at->format('F j, Y'),
                'ordered_time' => $order->created_at->format('g:i A'),
                'date_needed' => $order->date_needed?->format('F j, Y'),
                'time_needed' => $order->time_needed
                    ? Carbon::parse($order->time_needed)->format('g:i A')
                    : null,
                'notes' => $order->notes,
            ],
            'reseller' => [
                'name' => $order->reseller->name,
                'business_name' => $order->reseller->business_name,
                'code' => $order->reseller->code,
                // Contact is the only reseller detail a printed receipt needs.
                'phone' => $order->reseller->phone,
            ],
            'items' => $order->items->map(fn ($item) => [
                'name' => $item->display_name,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'line_total' => (float) $item->line_total,
            ])->all(),
            'totals' => [
                'subtotal' => (float) $order->subtotal,
                'discount' => (float) $order->discount,
                'delivery_fee' => (float) $order->delivery_fee,
                'total' => (float) $order->total,
                'downpayment_percent' => (int) $order->downpayment_percent,
                'downpayment_required' => (float) $order->downpayment_required,
                'amount_paid' => (float) $order->amount_paid,
                'balance' => (float) $order->balance,
            ],
            'payments' => $payments,
            'stamp' => $this->stampFor($order),
        ];
    }

    /**
     * The stamp must never read PAID while money is still owed. That is the
     * whole reason downpayment and balance receipts are separate documents.
     */
    public function stampFor(ResellerOrder $order, ?Payment $payment = null): array
    {
        if ($payment && in_array($payment->status, [Payment::STATUS_VOID, Payment::STATUS_CANCELLED], true)) {
            return ['label' => 'VOID', 'tone' => 'muted-red'];
        }

        if ($payment && $payment->status === Payment::STATUS_REFUNDED) {
            return ['label' => 'REFUNDED', 'tone' => 'muted-red'];
        }

        if ($order->isCancelled()) {
            return ['label' => 'CANCELLED', 'tone' => 'muted-red'];
        }

        return match ($order->payment_status) {
            ResellerOrder::PAY_FULL => ['label' => 'FULLY PAID', 'tone' => 'green'],
            ResellerOrder::PAY_DOWNPAYMENT => [
                'label' => sprintf('%d%% DOWNPAYMENT PAID', $order->downpayment_percent),
                'tone' => 'muted-green',
            ],
            ResellerOrder::PAY_PARTIAL => ['label' => 'PARTIALLY PAID', 'tone' => 'amber'],
            ResellerOrder::PAY_REFUNDED => ['label' => 'REFUNDED', 'tone' => 'muted-red'],
            ResellerOrder::PAY_VOID => ['label' => 'VOID', 'tone' => 'muted-red'],
            default => ['label' => 'PENDING PAYMENT', 'tone' => 'amber'],
        };
    }
}
