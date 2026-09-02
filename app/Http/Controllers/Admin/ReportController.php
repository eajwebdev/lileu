<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\PosSale;
use App\Models\Purchase;
use App\Models\Reseller;
use App\Models\ResellerOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = ($request->date('to') ?? now())->endOfDay();

        $posSales = (float) PosSale::where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])->sum('total');
        $collected = (float) Payment::where('status', Payment::STATUS_PAID)
            ->whereBetween('paid_at', [$from, $to])->sum('amount');
        $purchases = (float) Purchase::whereBetween('purchased_on', [$from, $to])->sum('amount');
        $expenses = (float) Expense::whereBetween('incurred_on', [$from, $to])->sum('amount');

        return Inertia::render('Admin/Reports/Index', [
            'range' => ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')],
            'summary' => [
                'pos_sales' => round($posSales, 2),
                'reseller_collected' => round($collected, 2),
                'gross_sales' => round($posSales + $collected, 2),
                'purchases' => round($purchases, 2),
                'expenses' => round($expenses, 2),
                'net_profit' => round($posSales + $collected - $purchases - $expenses, 2),
                'orders_placed' => ResellerOrder::whereBetween('created_at', [$from, $to])->count(),
                'receivables' => round((float) ResellerOrder::where('status', '!=', ResellerOrder::STATUS_CANCELLED)
                    ->sum('balance'), 2),
            ],
            'byPaymentMethod' => Payment::where('status', Payment::STATUS_PAID)
                ->whereBetween('paid_at', [$from, $to])
                ->select('method', DB::raw('SUM(amount) as amount'), DB::raw('COUNT(*) as count'))
                ->groupBy('method')
                ->get()
                ->map(fn ($r) => [
                    'method' => $r->method,
                    'amount' => round((float) $r->amount, 2),
                    'count' => (int) $r->count,
                ]),
            'topResellers' => Reseller::query()
                ->select('resellers.id', 'resellers.name', 'resellers.business_name', 'resellers.code')
                ->selectRaw('COALESCE(SUM(reseller_orders.total), 0) as ordered')
                ->leftJoin('reseller_orders', function ($join) use ($from, $to) {
                    $join->on('reseller_orders.reseller_id', '=', 'resellers.id')
                        ->whereBetween('reseller_orders.created_at', [$from, $to])
                        ->where('reseller_orders.status', '!=', ResellerOrder::STATUS_CANCELLED);
                })
                ->groupBy('resellers.id', 'resellers.name', 'resellers.business_name', 'resellers.code')
                ->orderByDesc('ordered')
                ->limit(10)
                ->get()
                ->map(fn ($r) => [
                    'name' => $r->business_name ?: $r->name,
                    'code' => $r->code,
                    'ordered' => round((float) $r->ordered, 2),
                ]),
            'productMix' => DB::table('reseller_order_items')
                ->join('reseller_orders', 'reseller_orders.id', '=', 'reseller_order_items.reseller_order_id')
                ->whereBetween('reseller_orders.created_at', [$from, $to])
                ->where('reseller_orders.status', '!=', ResellerOrder::STATUS_CANCELLED)
                ->groupBy('reseller_order_items.product_name')
                ->select(
                    'reseller_order_items.product_name as name',
                    DB::raw('SUM(reseller_order_items.quantity) as qty'),
                    DB::raw('SUM(reseller_order_items.line_total) as revenue'),
                )
                ->orderByDesc('qty')
                ->limit(10)
                ->get()
                ->map(fn ($r) => [
                    'name' => $r->name,
                    'qty' => (int) $r->qty,
                    'revenue' => round((float) $r->revenue, 2),
                ]),
        ]);
    }
}
