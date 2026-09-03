<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\Purchase;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    public function __construct(private PurchaseService $purchases) {}

    public function index(Request $request): Response
    {
        $month = $request->string('month')->toString() ?: now()->format('Y-m');
        [$year, $m] = array_map('intval', explode('-', $month));
        $from = now()->setDate($year, $m, 1)->startOfMonth();
        $to = $from->copy()->endOfMonth();

        $purchases = Purchase::query()
            ->with(['recorder:id,name', 'items:id,purchase_id,name,unit,quantity,unit_price,line_total'])
            ->whereBetween('purchased_on', [$from, $to])
            ->when($request->string('q')->toString(), fn ($q, $term) => $q
                ->where(fn ($w) => $w->where('supplier', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhere('reference', 'like', "%{$term}%")
                    ->orWhereHas('items', fn ($i) => $i->where('name', 'like', "%{$term}%"))))
            ->orderByDesc('purchased_on')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Purchase $p) => [
                'id' => $p->id,
                'purchased_on' => $p->purchased_on->format('M j, Y'),
                'supplier' => $p->supplier,
                'reference' => $p->reference,
                'description' => $p->description,
                'notes' => $p->notes,
                'amount' => (float) $p->amount,
                'recorded_by' => $p->recorder?->name,
                'items' => $p->items->map(fn ($i) => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'unit' => $i->unit,
                    'quantity' => (float) $i->quantity,
                    'unit_price' => (float) $i->unit_price,
                    'line_total' => (float) $i->line_total,
                ]),
            ]);

        return Inertia::render('Admin/Purchases/Index', [
            'purchases' => $purchases,
            'month' => $month,
            'filters' => $request->only('q'),
            'ingredients' => $this->pickerList(),
            'units' => Ingredient::UNITS,
            'totals' => [
                'amount' => round((float) Purchase::whereBetween('purchased_on', [$from, $to])->sum('amount'), 2),
                'count' => Purchase::whereBetween('purchased_on', [$from, $to])->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Purchases/Create', [
            'ingredients' => $this->pickerList(),
            'units' => Ingredient::UNITS,
            'categories' => Ingredient::CATEGORIES,
            'suppliers' => Purchase::query()
                ->whereNotNull('supplier')
                ->distinct()
                ->orderBy('supplier')
                ->limit(50)
                ->pluck('supplier'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'purchased_on' => ['required', 'date'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['nullable', 'integer', 'exists:ingredients,id'],
            // A line names a new ingredient only when it is not picked from the list.
            'items.*.name' => ['nullable', 'required_without:items.*.ingredient_id', 'string', 'max:255'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:1000000'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:10000000'],
        ]);

        $purchase = $this->purchases->record($data['items'], $data, $request->user());

        return redirect()
            ->route('admin.purchases.index', ['month' => $purchase->purchased_on->format('Y-m')])
            ->with('success', 'Purchase recorded and ingredient prices updated.');
    }

    public function destroy(Purchase $purchase): RedirectResponse
    {
        // Items cascade; ingredient prices stay where they are, since the price
        // last paid is still the best guess for the next purchase.
        $purchase->delete();

        return back()->with('success', 'Purchase removed.');
    }

    /**
     * The reusable catalog, shaped for the line-item picker: every ingredient
     * arrives with the price last paid so the form prefills it.
     */
    private function pickerList()
    {
        return Ingredient::active()
            ->orderBy('name')
            ->get(['id', 'name', 'unit', 'category', 'supplier', 'last_price', 'last_purchased_on'])
            ->map(fn (Ingredient $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'unit' => $i->unit,
                'category' => $i->category,
                'supplier' => $i->supplier,
                'last_price' => (float) $i->last_price,
                'last_purchased_on' => $i->last_purchased_on?->format('M j, Y'),
            ]);
    }
}
