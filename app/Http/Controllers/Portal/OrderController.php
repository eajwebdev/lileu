<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Reseller;
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
        private OrderService $orders,
        private PaymentService $payments,
    ) {}

    public function index(Request $request): Response
    {
        $orders = $request->user()->reseller
            ->orders()
            ->withCount('items')
            ->latest()
            ->paginate(12)
            ->through(fn (ResellerOrder $order) => [
                'number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'total' => (float) $order->total,
                'amount_paid' => (float) $order->amount_paid,
                'balance' => (float) $order->balance,
                'items_count' => $order->items_count,
                'placed_on' => $order->created_at->format('M j, Y'),
                'date_needed' => $order->date_needed?->format('M j, Y'),
                'fulfillment_type' => $order->fulfillment_type,
            ]);

        return Inertia::render('Portal/Orders/Index', ['orders' => $orders]);
    }

    public function create(Request $request): Response
    {
        $reseller = $request->user()->reseller;

        return Inertia::render('Portal/Orders/Create', [
            'products' => $this->orderableProducts($reseller),
            'reseller' => [
                'discount_percent' => $reseller->discount_percent,
                'downpayment_percent' => $reseller->downpayment_percent,
                'address' => $reseller->address,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $reseller = $request->user()->reseller;

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'fulfillment_type' => ['required', 'in:pickup,delivery'],
            'delivery_address' => ['nullable', 'required_if:fulfillment_type,delivery', 'string', 'max:500'],
            'date_needed' => ['required', 'date', 'after_or_equal:today'],
            'time_needed' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // A reseller may only order what admin has approved for them.
        $allowed = $this->orderableProducts($reseller)->pluck('id')->all();
        $lines = collect($data['items'])
            ->filter(fn ($line) => in_array((int) $line['product_id'], $allowed, true))
            ->values()
            ->all();

        if ($lines === []) {
            return back()->with('error', 'None of those products are available on your account right now.');
        }

        $order = $this->orders->placeResellerOrder($reseller, $lines, $data);

        return redirect()
            ->route('portal.orders.show', $order->order_number)
            ->with('success', "Order {$order->order_number} placed. Settle the downpayment to confirm it.");
    }

    public function show(Request $request, ResellerOrder $order): Response
    {
        $this->authorizeOrder($request, $order);

        $order->load(['items', 'payments' => fn ($q) => $q->latest()]);

        return Inertia::render('Portal/Orders/Show', [
            'order' => [
                'number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'fulfillment_type' => $order->fulfillment_type,
                'delivery_address' => $order->delivery_address,
                'placed_on' => $order->created_at->format('F j, Y'),
                'placed_time' => $order->created_at->format('g:i A'),
                'date_needed' => $order->date_needed?->format('F j, Y'),
                'notes' => $order->notes,
                'subtotal' => (float) $order->subtotal,
                'discount' => (float) $order->discount,
                'delivery_fee' => (float) $order->delivery_fee,
                'total' => (float) $order->total,
                'downpayment_percent' => (int) $order->downpayment_percent,
                'downpayment_required' => (float) $order->downpayment_required,
                'amount_paid' => (float) $order->amount_paid,
                'balance' => (float) $order->balance,
                'due_now' => $order->amountDueNow(),
                'items' => $order->items->map(fn ($i) => [
                    'name' => $i->product_name,
                    'quantity' => $i->quantity,
                    'unit_price' => (float) $i->unit_price,
                    'line_total' => (float) $i->line_total,
                ]),
                'payments' => $order->payments->map(fn (Payment $p) => [
                    'receipt_number' => $p->receipt_number,
                    'kind_label' => $p->kindLabel(),
                    'method_label' => $p->methodLabel(),
                    'status' => $p->status,
                    'amount' => (float) $p->amount,
                    'paid_on' => $p->paid_at?->format('M j, Y g:i A'),
                ]),
            ],
        ]);
    }

    /** Opens (or reuses) the pending payment and sends the reseller to QR Ph. */
    public function pay(Request $request, ResellerOrder $order): RedirectResponse
    {
        $this->authorizeOrder($request, $order);

        if ($order->isCancelled()) {
            return back()->with('error', 'This order was cancelled and can no longer be paid.');
        }

        if ((float) $order->balance <= 0) {
            return back()->with('info', 'This order is already fully paid.');
        }

        $payment = $this->payments->openPayment($order);

        return redirect()->route('pay.show', $payment->receipt_number);
    }

    private function orderableProducts(Reseller $reseller)
    {
        $curated = $reseller->products()
            ->wherePivot('is_approved', true)
            ->where('products.is_active', true)
            ->get();

        // No curated list yet? Fall back to everything opened to resellers, so a
        // freshly approved partner is never staring at an empty catalog.
        $products = $curated->isNotEmpty()
            ? $curated->where('is_available', true)->values()
            : Product::active()->where('is_available', true)->where('available_to_resellers', true)->get();

        return $products->map(fn (Product $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku,
            'image_url' => $p->image_url,
            'description' => $p->description,
            'unit_price' => $this->orders->priceFor($reseller, $p),
            'retail_price' => (float) $p->retail_price,
            'min_qty' => $p->min_reseller_qty,
            'in_stock' => $p->isAvailable(),
            'made_to_order' => ! $p->tracksStock(),
            'tracks_stock' => $p->tracksStock(),
            'stock' => $p->stock,
        ])->values();
    }

    private function authorizeOrder(Request $request, ResellerOrder $order): void
    {
        abort_unless($order->reseller_id === $request->user()->reseller?->id, 403);
    }
}
