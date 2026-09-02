<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\PosSale;
use App\Models\Product;
use App\Services\NumberGenerator;
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
    public function __construct(private NumberGenerator $numbers) {}

    public function index(): Response
    {
        return Inertia::render('Pos/Terminal', [
            'products' => Product::active()
                ->with('category:id,name,accent')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (Product $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'sku' => $p->sku,
                    'image_url' => $p->image_url,
                    'price' => (float) $p->retail_price,
                    'stock' => $p->stock,
                    'tracks_stock' => $p->tracksStock(),
                    'category_id' => $p->category_id,
                    'category' => $p->category?->name,
                    'accent' => $p->category?->accent ?? 'blush',
                ]),
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
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'method' => ['required', 'in:cash,gcash,qrph,card'],
            'amount_tendered' => ['nullable', 'numeric', 'min:0'],
            'customer_name' => ['nullable', 'string', 'max:120'],
        ]);

        $sale = DB::transaction(function () use ($data, $request) {
            $products = Product::whereIn('id', collect($data['items'])->pluck('product_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $subtotal = 0.0;
            $rows = [];

            foreach ($data['items'] as $line) {
                $product = $products->get((int) $line['product_id']);

                if (! $product) {
                    throw ValidationException::withMessages([
                        'items' => 'One of the selected products is no longer available.',
                    ]);
                }

                $qty = (int) $line['quantity'];

                if ($product->tracksStock() && $qty > $product->stock) {
                    throw ValidationException::withMessages([
                        'items' => "{$product->name}: only {$product->stock} pcs are currently in stock.",
                    ]);
                }

                $lineTotal = round((float) $product->retail_price * $qty, 2);
                $subtotal += $lineTotal;

                $rows[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $qty,
                    'unit_price' => (float) $product->retail_price,
                    'line_total' => $lineTotal,
                ];

                if ($product->tracksStock()) {
                    $product->decrement('stock', $qty);
                }
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
