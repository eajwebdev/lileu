<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\PosSale;
use App\Models\Product;
use App\Services\NumberGenerator;
use App\Services\SellableResolver;
use App\Support\Catalog;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class TerminalController extends Controller
{
    public function __construct(
        private NumberGenerator $numbers,
        private SellableResolver $sellables,
    ) {}

    /**
     * 'distinct' cannot express this: the same product twice is fine when the
     * flavours differ, but the same flavour twice would double-count stock.
     */
    private function assertNoRepeatedLines(array $items): void
    {
        $keys = collect($items)->map(fn ($line) => $this->sellables->keyForLine($line));

        if ($keys->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'The same item was added twice — combine it into one line.',
            ]);
        }
    }

    public function index(): Response
    {
        return Inertia::render('Pos/Terminal', [
            // One tile per sellable: a flavour is tapped directly rather than
            // hidden behind the product it belongs to.
            'products' => Catalog::sellables(Catalog::activeProducts()),
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'accent']),
            'todayTotal' => round((float) PosSale::where('status', 'completed')
                ->whereDate('created_at', today())->sum('total'), 2),
            'todayCount' => PosSale::where('status', 'completed')->whereDate('created_at', today())->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'method' => ['required', 'in:cash,gcash,qrph,card'],
            'amount_tendered' => ['nullable', 'numeric', 'min:0'],
            'customer_name' => ['nullable', 'string', 'max:120'],
        ]);

        $sale = DB::transaction(function () use ($data, $request) {
            // A flavour is its own line on the receipt, so the same product
            // may legitimately appear twice under two flavours.
            $this->assertNoRepeatedLines($data['items']);

            $sellables = $this->sellables->resolve($data['items'], lock: true);

            $subtotal = 0.0;
            $rows = [];

            foreach ($data['items'] as $line) {
                $sellable = $sellables->get($this->sellables->keyForLine($line));

                if (! $sellable) {
                    throw ValidationException::withMessages([
                        'items' => 'One of the selected products is no longer available.',
                    ]);
                }

                $qty = (int) $line['quantity'];
                $label = $sellable->sellableLabel();

                if (! $sellable->isManuallyAvailable()) {
                    throw ValidationException::withMessages([
                        'items' => "{$label} is currently unavailable.",
                    ]);
                }

                if ($sellable->tracksStock() && $qty > $sellable->availableStock()) {
                    $have = $sellable->availableStock();

                    throw ValidationException::withMessages([
                        'items' => "{$label}: only {$have} pcs are currently in stock.",
                    ]);
                }

                $unitPrice = $sellable->retailPrice();
                $lineTotal = round($unitPrice * $qty, 2);
                $subtotal += $lineTotal;

                $rows[] = [
                    'product_id' => $sellable->sellableProductId(),
                    'product_variant_id' => $sellable->sellableVariantId(),
                    'product_name' => $sellable->sellableProductName(),
                    'variant_name' => $sellable->sellableVariantName(),
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];

                $sellable->decrementStock($qty);
            }

            $discount = round((float) ($data['discount'] ?? 0), 2);

            if ($discount > $subtotal) {
                throw ValidationException::withMessages([
                    'discount' => 'Discount cannot be greater than the subtotal.',
                ]);
            }

            $total = round(max($subtotal - $discount, 0), 2);
            $tendered = $data['method'] === 'cash'
                ? round((float) ($data['amount_tendered'] ?? $total), 2)
                : $total;

            if ($data['method'] === 'cash' && $tendered < $total) {
                throw ValidationException::withMessages([
                    'amount_tendered' => 'Cash received is less than the sale total.',
                ]);
            }

            $sale = PosSale::create([
                'sale_number' => $this->numbers->posSaleNumber(),
                'cashier_id' => $request->user()->id,
                'customer_name' => $data['customer_name'] ?? null,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'amount_tendered' => $tendered,
                'change_due' => round(max($tendered - $total, 0), 2),
                'method' => $data['method'],
                'status' => 'completed',
            ]);

            $sale->items()->createMany($rows);

            return $sale;
        });

        return redirect()
            ->route('pos.index')
            ->with('success', "Sale {$sale->sale_number} completed.")
            ->with('info', $sale->sale_number);
    }

    /** 80mm thermal slip — the cashier-side counterpart of the A4 receipt. */
    public function receipt(PosSale $sale): View
    {
        $sale->load(['items', 'cashier:id,name']);

        return view('receipts.pos', [
            'sale' => $sale,
            'brand' => Settings::brand(),
            'options' => Settings::receipt(),
            'autoPrint' => true,
        ]);
    }

    public function history(Request $request): Response
    {
        $sales = PosSale::with('cashier:id,name')
            ->withCount('items')
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where('sale_number', 'like', "%{$term}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (PosSale $s) => [
                'sale_number' => $s->sale_number,
                'cashier' => $s->cashier?->name,
                'customer_name' => $s->customer_name,
                'items_count' => $s->items_count,
                'total' => (float) $s->total,
                'method' => $s->method,
                'status' => $s->status,
                'sold_at' => $s->created_at->format('M j, Y g:i A'),
            ]);

        return Inertia::render('Pos/History', [
            'sales' => $sales,
            'filters' => $request->only('q'),
            'todayTotal' => round((float) PosSale::where('status', 'completed')
                ->whereDate('created_at', today())->sum('total'), 2),
        ]);
    }
}
