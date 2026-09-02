<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $payments = Payment::query()
            ->with(['order:id,order_number,reseller_id', 'order.reseller:id,name,business_name'])
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q
                ->where(fn ($w) => $w->where('receipt_number', 'like', "%{$term}%")
                    ->orWhere('reference', 'like', "%{$term}%")))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Payment $p) => [
                'id' => $p->id,
                'receipt_number' => $p->receipt_number,
                'order_number' => $p->order?->order_number,
                'reseller' => $p->order?->reseller?->business_name ?: $p->order?->reseller?->name,
                'kind_label' => $p->kindLabel(),
                'method_label' => $p->methodLabel(),
                'status' => $p->status,
                'amount' => (float) $p->amount,
                'reference' => $p->reference,
                'paid_on' => $p->paid_at?->format('M j, Y g:i A'),
                'created_on' => $p->created_at->format('M j, Y'),
            ]);

        return Inertia::render('Admin/Payments/Index', [
            'payments' => $payments,
            'filters' => $request->only(['status', 'q']),
            'totals' => [
                'collected' => round((float) Payment::where('status', Payment::STATUS_PAID)->sum('amount'), 2),
                'pending' => round((float) Payment::where('status', Payment::STATUS_PENDING)->sum('amount'), 2),
            ],
        ]);
    }
}
