<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Consignment;
use App\Models\ConsignmentItem;
use App\Models\ConsignmentSettlement;
use App\Models\Product;
use App\Models\Reseller;
use App\Services\ConsignmentService;
use App\Support\Catalog;
use App\Support\Settings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class ConsignmentController extends Controller
{
    public function __construct(private ConsignmentService $consignments) {}

    public function index(Request $request): Response
    {
        $consignments = Consignment::query()
            ->with('reseller:id,name,business_name,code')
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q
                ->where(fn ($w) => $w->where('consignment_number', 'like', "%{$term}%")
                    ->orWhereHas('reseller', fn ($r) => $r->where('name', 'like', "%{$term}%")
                        ->orWhere('business_name', 'like', "%{$term}%"))))
            ->latest('issued_on')
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Consignment $c) => [
                'number' => $c->consignment_number,
                'seller' => $c->reseller?->business_name ?: $c->reseller?->name,
                'seller_code' => $c->reseller?->code,
                'status' => $c->status,
                'issued_on' => $c->issued_on->format('M j, Y'),
                'due_on' => $c->due_on?->format('M j, Y'),
                'quantity_issued' => $c->quantity_issued,
                'quantity_sold' => $c->quantity_sold,
                'outstanding' => $c->outstandingQuantity(),
                'issued_value' => (float) $c->issued_value,
                'sold_value' => (float) $c->sold_value,
                'amount_collected' => (float) $c->amount_collected,
                'amount_due' => (float) $c->amount_due,
            ]);

        $open = Consignment::where('status', Consignment::STATUS_OPEN);

        return Inertia::render('Admin/Consignments/Index', [
            'consignments' => $consignments,
            'filters' => $request->only(['status', 'q']),
            'summary' => [
                'open_batches' => (clone $open)->count(),
                'value_out' => round((float) (clone $open)->sum('issued_value'), 2),
                'due' => round((float) Consignment::where('status', '!=', Consignment::STATUS_CANCELLED)
                    ->sum('amount_due'), 2),
                'losses' => round((float) Consignment::sum('loss_value'), 2),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Consignments/Create', [
            'sellers' => Reseller::query()
                ->whereIn('status', [Reseller::STATUS_APPROVED])
                ->with('products:id')
                ->orderBy('name')
                ->get()
                ->map(fn (Reseller $r) => [
                    'id' => $r->id,
                    'label' => $r->business_name ?: $r->name,
                    'name' => $r->name,
                    'code' => $r->code,
                    'phone' => $r->phone,
                    'engagement' => $r->engagement,
                    // Rates negotiated with this seller, keyed by product. The
                    // deal is struck per product, so every flavour inherits it.
                    'prices' => $r->products
                        ->filter(fn ($p) => $p->pivot->custom_price !== null)
                        ->mapWithKeys(fn ($p) => [$p->id => (float) $p->pivot->custom_price]),
                ]),
            // Flavours are issued individually, so the picker lists sellables.
            'products' => Catalog::sellables(Catalog::activeProducts()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reseller_id' => [
                'required',
                Rule::exists('resellers', 'id')->where('status', Reseller::STATUS_APPROVED),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'issued_on' => ['required', 'date'],
            'due_on' => ['nullable', 'date', 'after_or_equal:issued_on'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $seller = Reseller::findOrFail($data['reseller_id']);

        $consignment = $this->consignments->issue($seller, $data['items'], $data, $request->user());

        return redirect()
            ->route('admin.consignments.show', $consignment->consignment_number)
            ->with('success', "Consignment {$consignment->consignment_number} issued to {$seller->name}.");
    }

    public function show(Consignment $consignment): Response
    {
        $consignment->load(['items', 'reseller', 'issuer:id,name', 'settlements.items']);

        return Inertia::render('Admin/Consignments/Show', [
            'consignment' => $this->payload($consignment),
            'seller' => [
                'id' => $consignment->reseller->id,
                'code' => $consignment->reseller->code,
                'name' => $consignment->reseller->name,
                'business_name' => $consignment->reseller->business_name,
                'phone' => $consignment->reseller->phone,
                'engagement' => $consignment->reseller->engagement,
            ],
        ]);
    }

    public function settle(Request $request, Consignment $consignment): RedirectResponse
    {
        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.consignment_item_id' => ['required', 'integer', 'distinct', 'exists:consignment_items,id'],
            'lines.*.sold' => ['nullable', 'integer', 'min:0'],
            'lines.*.returned' => ['nullable', 'integer', 'min:0'],
            'lines.*.expired' => ['nullable', 'integer', 'min:0'],
            'lines.*.damaged' => ['nullable', 'integer', 'min:0'],
            'lines.*.missing' => ['nullable', 'integer', 'min:0'],
            'settled_on' => ['required', 'date'],
            'amount_collected' => ['nullable', 'numeric', 'min:0'],
            'method' => ['required', 'in:cash,gcash,qrph,bank_transfer'],
            'reference' => ['nullable', 'string', 'max:80'],
            'is_final' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $settlement = $this->consignments->settle($consignment, $data['lines'], $data, $request->user());

        return back()->with('success', "Collection recorded. Receipt {$settlement->receipt_number}.");
    }

    public function cancel(Consignment $consignment): RedirectResponse
    {
        $this->consignments->cancel($consignment);

        return back()->with('success', 'Consignment cancelled; tracked stock was restored.');
    }

    /** The hand-over slip the seller signs when they take the goods. */
    public function slip(Request $request, Consignment $consignment): View|HttpResponse
    {
        $consignment->load(['items', 'reseller', 'issuer:id,name']);

        $view = [
            'data' => $this->payload($consignment),
            'brand' => Settings::brand(),
            'options' => Settings::receipt(),
        ];

        if ($request->boolean('pdf')) {
            return Pdf::loadView('receipts.consignment-slip', $view + ['isPdf' => true])
                ->setPaper('a4')
                ->download("{$consignment->consignment_number}.pdf");
        }

        return view('receipts.consignment-slip', $view + ['autoPrint' => true, 'isPdf' => false]);
    }

    /** The receipt for one collection event. */
    public function settlementReceipt(Request $request, ConsignmentSettlement $settlement): View|HttpResponse
    {
        $settlement->load(['items', 'consignment.reseller', 'recorder:id,name']);

        $view = [
            'settlement' => $this->settlementPayload($settlement),
            'data' => $this->payload($settlement->consignment),
            'brand' => Settings::brand(),
            'options' => Settings::receipt(),
        ];

        if ($request->boolean('pdf')) {
            return Pdf::loadView('receipts.consignment-settlement', $view + ['isPdf' => true])
                ->setPaper('a4')
                ->download("{$settlement->receipt_number}.pdf");
        }

        return view('receipts.consignment-settlement', $view + ['autoPrint' => true, 'isPdf' => false]);
    }

    private function payload(Consignment $consignment): array
    {
        $consignment->loadMissing(['items', 'reseller', 'issuer:id,name', 'settlements.items']);

        return [
            'id' => $consignment->id,
            'number' => $consignment->consignment_number,
            'status' => $consignment->status,
            'issued_on' => $consignment->issued_on->format('F j, Y'),
            'due_on' => $consignment->due_on?->format('F j, Y'),
            'settled_at' => $consignment->settled_at?->format('F j, Y g:i A'),
            'issued_by' => $consignment->issuer?->name,
            'notes' => $consignment->notes,
            'seller_name' => $consignment->reseller->business_name ?: $consignment->reseller->name,
            'seller_code' => $consignment->reseller->code,
            'seller_phone' => $consignment->reseller->phone,
            'totals' => [
                'quantity_issued' => $consignment->quantity_issued,
                'quantity_sold' => $consignment->quantity_sold,
                'quantity_returned' => $consignment->quantity_returned,
                'quantity_expired' => $consignment->quantity_expired,
                'quantity_damaged' => $consignment->quantity_damaged,
                'quantity_missing' => $consignment->quantity_missing,
                'outstanding' => $consignment->outstandingQuantity(),
                'issued_value' => (float) $consignment->issued_value,
                'sold_value' => (float) $consignment->sold_value,
                'loss_value' => (float) $consignment->loss_value,
                'amount_collected' => (float) $consignment->amount_collected,
                'amount_due' => (float) $consignment->amount_due,
            ],
            'items' => $consignment->items->map(fn (ConsignmentItem $i) => [
                'id' => $i->id,
                'name' => $i->display_name,
                'sku' => $i->sku,
                'unit_price' => (float) $i->unit_price,
                'retail_price' => (float) $i->retail_price,
                'margin' => round((float) $i->retail_price - (float) $i->unit_price, 2),
                'tracks_stock' => $i->tracks_stock,
                'quantity_issued' => $i->quantity_issued,
                'quantity_sold' => $i->quantity_sold,
                'quantity_returned' => $i->quantity_returned,
                'quantity_expired' => $i->quantity_expired,
                'quantity_damaged' => $i->quantity_damaged,
                'quantity_missing' => $i->quantity_missing,
                'outstanding' => $i->outstanding(),
                'sold_value' => (float) $i->sold_value,
            ])->values(),
            'settlements' => $consignment->settlements->sortBy('settled_on')->values()
                ->map(fn (ConsignmentSettlement $s) => [
                    'id' => $s->id,
                    'receipt_number' => $s->receipt_number,
                    'settled_on' => $s->settled_on->format('M j, Y'),
                    'method_label' => $s->methodLabel(),
                    'reference' => $s->reference,
                    'sold_value' => (float) $s->sold_value,
                    'amount_collected' => (float) $s->amount_collected,
                    'is_final' => $s->is_final,
                    'lines' => $s->items->map(fn ($i) => [
                        'name' => $i->display_name,
                        'sold' => $i->quantity_sold,
                        'returned' => $i->quantity_returned,
                        'expired' => $i->quantity_expired,
                        'damaged' => $i->quantity_damaged,
                        'missing' => $i->quantity_missing,
                        'sold_value' => (float) $i->sold_value,
                    ]),
                ]),
            'stamp' => $this->consignments->stampFor($consignment),
        ];
    }

    private function settlementPayload(ConsignmentSettlement $settlement): array
    {
        return [
            'receipt_number' => $settlement->receipt_number,
            'settled_on' => $settlement->settled_on->format('F j, Y'),
            'method_label' => $settlement->methodLabel(),
            'reference' => $settlement->reference,
            'sold_value' => (float) $settlement->sold_value,
            'amount_collected' => (float) $settlement->amount_collected,
            'is_final' => $settlement->is_final,
            'recorded_by' => $settlement->recorder?->name,
            'notes' => $settlement->notes,
            'items' => $settlement->items->map(fn ($i) => [
                'name' => $i->display_name,
                'sold' => $i->quantity_sold,
                'returned' => $i->quantity_returned,
                'expired' => $i->quantity_expired,
                'damaged' => $i->quantity_damaged,
                'missing' => $i->quantity_missing,
                'unit_price' => (float) $i->unit_price,
                'sold_value' => (float) $i->sold_value,
            ]),
        ];
    }
}
