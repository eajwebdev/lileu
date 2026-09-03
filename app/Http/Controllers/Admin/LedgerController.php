<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Purchase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Overhead expenses, plus a read-only view of the month's ingredient buying.
 * Purchases are written by the itemised flow in {@see PurchaseController}, so
 * their total here is always the sum of real ingredient lines.
 */
class LedgerController extends Controller
{
    public function index(Request $request): Response
    {
        $month = $request->string('month')->toString() ?: now()->format('Y-m');
        [$year, $m] = array_map('intval', explode('-', $month));
        $from = now()->setDate($year, $m, 1)->startOfMonth();
        $to = $from->copy()->endOfMonth();

        return Inertia::render('Admin/Ledger/Index', [
            'month' => $month,
            'expenses' => Expense::with('recorder:id,name')
                ->whereBetween('incurred_on', [$from, $to])
                ->orderByDesc('incurred_on')
                ->get()
                ->map(fn (Expense $e) => [
                    'id' => $e->id,
                    'incurred_on' => $e->incurred_on->format('M j, Y'),
                    'category' => $e->category,
                    'description' => $e->description,
                    'amount' => (float) $e->amount,
                    'recorded_by' => $e->recorder?->name,
                ]),
            'purchases' => Purchase::with('items:id,purchase_id,name,unit,quantity,unit_price,line_total')
                ->whereBetween('purchased_on', [$from, $to])
                ->orderByDesc('purchased_on')
                ->get()
                ->map(fn (Purchase $p) => [
                    'id' => $p->id,
                    'purchased_on' => $p->purchased_on->format('M j, Y'),
                    'supplier' => $p->supplier,
                    'reference' => $p->reference,
                    'description' => $p->description,
                    'amount' => (float) $p->amount,
                    'item_count' => $p->items->count(),
                ]),
            'totals' => [
                'expenses' => round((float) Expense::whereBetween('incurred_on', [$from, $to])->sum('amount'), 2),
                'purchases' => round((float) Purchase::whereBetween('purchased_on', [$from, $to])->sum('amount'), 2),
            ],
        ]);
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'incurred_on' => ['required', 'date'],
            'category' => ['required', 'string', 'max:60'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        Expense::create($data + ['recorded_by' => $request->user()->id]);

        return back()->with('success', 'Expense recorded.');
    }

    public function destroyExpense(Expense $expense): RedirectResponse
    {
        $expense->delete();

        return back()->with('success', 'Expense removed.');
    }
}
