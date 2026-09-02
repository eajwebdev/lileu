<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Payment;
use App\Models\ResellerOrder;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(
        private PaymentService $payments,
        private OrderService $orders,
    ) {}

    public function index(Request $request): Response
    {
        $orders = ResellerOrder::query()
            ->with('reseller:id,name,business_name,code')
            ->withCount('items')
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('payment_status')->toString(), fn ($q, $s) => $q->where('payment_status', $s))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q
                ->where(fn ($w) => $w->where('order_number', 'like', "%{$term}%")
                    ->orWhereHas('reseller', fn ($r) => $r->where('name', 'like', "%{$term}%")
                        ->orWhere('business_name', 'like', "%{$term}%"))))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ResellerOrder $o) => [
                'number' => $o->order_number,
                'reseller' => $o->reseller?->business_name ?: $o->reseller?->name,
                'reseller_code' => $o->reseller?->code,
                'status' => $o->status,
                'payment_status' => $o->payment_status,
                'fulfillment_type' => $o->fulfillment_type,
                'items_count' => $o->items_count,
                'total' => (float) $o->total,
                'amount_paid' => (float) $o->amount_paid,
                'balance' => (float) $o->balance,
                'placed_on' => $o->created_at->format('M j, Y'),
                'date_needed' => $o->date_needed?->format('M j, Y'),
            ]);

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'filters' => $request->only(['status', 'payment_status', 'q']),
            'summary' => [
                'receivables' => round((float) ResellerOrder::where('status', '!=', ResellerOrder::STATUS_CANCELLED)
                    ->sum('balance'), 2),
                'open' => ResellerOrder::whereNotIn('status', [
                    ResellerOrder::STATUS_COMPLETED, ResellerOrder::STATUS_CANCELLED,
                ])->count(),
            ],
        ]);
    }

    public function show(ResellerOrder $order): Response
    {
        $order->load(['items', 'reseller', 'payments' => fn ($q) => $q->latest()]);

        return Inertia::render('Admin/Orders/Show', [
            'order' => [
                'id' => $order->id,
                'number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'fulfillment_type' => $order->fulfillment_type,
                'delivery_address' => $order->delivery_address,
                'placed_on' => $order->created_at->format('F j, Y g:i A'),
                'date_needed' => $order->date_needed?->format('F j, Y'),
                'notes' => $order->notes,
                'admin_notes' => $order->admin_notes,
                'subtotal' => (float) $order->subtotal,
                'discount' => (float) $order->discount,
                'delivery_fee' => (float) $order->delivery_fee,
                'total' => (float) $order->total,
                'downpayment_percent' => (int) $order->downpayment_percent,
                'downpayment_required' => (float) $order->downpayment_required,
                'amount_paid' => (float) $order->amount_paid,
                'balance' => (float) $order->balance,
                'items' => $order->items->map(fn ($i) => [
                    'name' => $i->product_name,
                    'sku' => $i->sku,
                    'quantity' => $i->quantity,
                    'unit_price' => (float) $i->unit_price,
                    'line_total' => (float) $i->line_total,
                ]),
                'payments' => $order->payments->map(fn (Payment $p) => [
                    'id' => $p->id,
                    'receipt_number' => $p->receipt_number,
                    'kind_label' => $p->kindLabel(),
                    'method_label' => $p->methodLabel(),
                    'status' => $p->status,
                    'amount' => (float) $p->amount,
                    'reference' => $p->reference,
                    'paid_on' => $p->paid_at?->format('M j, Y g:i A'),
                ]),
            ],
            'reseller' => [
                'id' => $order->reseller->id,
                'code' => $order->reseller->code,
                'name' => $order->reseller->name,
                'business_name' => $order->reseller->business_name,
                'phone' => $order->reseller->phone,
                'email' => $order->reseller->email,
            ],
        ]);
    }

    public function updateStatus(Request $request, ResellerOrder $order): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,confirmed,preparing,ready,completed,cancelled'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $order->update([
            'status' => $data['status'],
            'admin_notes' => $data['admin_notes'] ?? $order->admin_notes,
            'confirmed_at' => $data['status'] === ResellerOrder::STATUS_CONFIRMED ? now() : $order->confirmed_at,
            'completed_at' => $data['status'] === ResellerOrder::STATUS_COMPLETED ? now() : $order->completed_at,
            'cancelled_at' => $data['status'] === ResellerOrder::STATUS_CANCELLED ? now() : $order->cancelled_at,
        ]);

        // Cancelling flips the payment status to void; syncTotals owns that rule.
        $this->orders->syncTotals($order);

        if (! empty($data['message'])) {
            Message::create([
                'reseller_id' => $order->reseller_id,
                'reseller_order_id' => $order->id,
                'user_id' => $request->user()->id,
                'author_role' => 'admin',
                'body' => $data['message'],
            ]);
        }

        return back()->with('success', "Order marked as {$data['status']}.");
    }

    /** Logs money collected outside PayMongo (cash on pickup, bank transfer). */
    public function recordPayment(Request $request, ResellerOrder $order): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.max((float) $order->balance, 0.01)],
            'method' => ['required', 'in:cash,bank_transfer,gcash,qrph,manual'],
            'reference' => ['nullable', 'string', 'max:80'],
        ]);

        $payment = $this->payments->recordManualPayment(
            $order,
            (float) $data['amount'],
            $data['method'],
            $data['reference'] ?? null,
            $request->user(),
        );

        return back()->with('success', "Payment recorded. Receipt {$payment->receipt_number}.");
    }

    public function voidPayment(Payment $payment): RedirectResponse
    {
        $this->payments->voidPayment($payment);

        return back()->with('success', "Receipt {$payment->receipt_number} voided.");
    }
}
