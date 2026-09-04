<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConsignmentSettlement;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\Reseller;
use App\Models\ResellerOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $range = $request->string('range')->toString() ?: '30d';
        [$from, $to] = $this->window($range);

        $posSales = (float) PosSale::where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])->sum('total');

        $resellerCollected = (float) Payment::where('status', Payment::STATUS_PAID)
            ->whereBetween('paid_at', [$from, $to])->sum('amount');

        // Cash handed over when a consigned batch is settled. It is money the
        // shop has actually taken, so it belongs in sales beside the counter
        // and the reseller channel.
        $consignmentCollected = (float) ConsignmentSettlement::whereBetween('settled_on', [$from, $to])
            ->sum('amount_collected');

        $purchases = (float) Purchase::whereBetween('purchased_on', [$from, $to])->sum('amount');
        $expenses = (float) Expense::whereBetween('incurred_on', [$from, $to])->sum('amount');

        // What the wholesale price gives away versus retail — the cost of the
        // reseller channel, reported as commissions.
        $commissions = (float) DB::table('reseller_order_items')
            ->join('reseller_orders', 'reseller_orders.id', '=', 'reseller_order_items.reseller_order_id')
            ->join('products', 'products.id', '=', 'reseller_order_items.product_id')
            ->leftJoin(
                'product_variants',
                'product_variants.id',
                '=',
                'reseller_order_items.product_variant_id',
            )
            ->whereBetween('reseller_orders.created_at', [$from, $to])
            ->where('reseller_orders.status', '!=', ResellerOrder::STATUS_CANCELLED)
            // The flavour sets retail when there is one; the product otherwise.
            ->sum(DB::raw(
                '(COALESCE(product_variants.retail_price, products.retail_price)'
                .' - reseller_order_items.unit_price) * reseller_order_items.quantity',
            ));

        $sales = round($posSales + $resellerCollected + $consignmentCollected, 2);

        return Inertia::render('Admin/Dashboard', [
            'range' => $range,
            'kpis' => [
                'sales' => $sales,
                'purchases' => round($purchases, 2),
                'expenses' => round($expenses, 2),
                'net_profit' => round($sales - $purchases - $expenses, 2),
                'commissions' => round($commissions, 2),
                'pos_sales' => round($posSales, 2),
                'reseller_collected' => round($resellerCollected, 2),
                'consignment_collected' => round($consignmentCollected, 2),
            ],
            'counters' => [
                'pending_applications' => Reseller::where('status', Reseller::STATUS_PENDING)->count(),
                'open_orders' => ResellerOrder::whereNotIn('status', [
                    ResellerOrder::STATUS_COMPLETED,
                    ResellerOrder::STATUS_CANCELLED,
                ])->count(),
                'awaiting_payment' => ResellerOrder::where('balance', '>', 0)
                    ->where('status', '!=', ResellerOrder::STATUS_CANCELLED)->count(),
                'receivables' => round((float) ResellerOrder::where('status', '!=', ResellerOrder::STATUS_CANCELLED)
                    ->sum('balance'), 2),
                'low_stock' => $this->lowStock()->count(),
            ],
            'salesTrend' => $this->salesTrend($from, $to),
            'topProducts' => $this->topProducts($from, $to),
            'recentOrders' => ResellerOrder::with('reseller:id,name,business_name')
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (ResellerOrder $o) => [
                    'number' => $o->order_number,
                    'reseller' => $o->reseller?->business_name ?: $o->reseller?->name,
                    'status' => $o->status,
                    'payment_status' => $o->payment_status,
                    'total' => (float) $o->total,
                    'balance' => (float) $o->balance,
                    'placed_on' => $o->created_at->format('M j'),
                ]),
            'lowStock' => $this->lowStock()->take(6)->values(),
        ]);
    }

    /**
     * Everything running low, whether it is a plain product or a flavour.
     * A product that sells by flavour holds no stock of its own, so counting
     * its zero would raise an alarm every single day.
     */
    private function lowStock()
    {
        $products = Product::active()
            ->where('tracks_stock', true)
            ->whereDoesntHave('variants', fn ($q) => $q->where('is_active', true))
            ->whereColumn('stock', '<=', 'low_stock_threshold')
            ->get(['id', 'name', 'stock', 'low_stock_threshold'])
            ->map(fn (Product $p) => [
                'id' => 'p'.$p->id,
                'name' => $p->name,
                'stock' => (int) $p->stock,
                'low_stock_threshold' => (int) $p->low_stock_threshold,
            ]);

        $variants = ProductVariant::query()
            ->where('product_variants.is_active', true)
            ->where('product_variants.tracks_stock', true)
            ->whereColumn('product_variants.stock', '<=', 'product_variants.low_stock_threshold')
            ->whereHas('product', fn ($q) => $q->where('is_active', true))
            ->with('product:id,name')
            ->get()
            ->map(fn (ProductVariant $v) => [
                'id' => 'v'.$v->id,
                'name' => $v->sellableLabel(),
                'stock' => (int) $v->stock,
                'low_stock_threshold' => (int) $v->low_stock_threshold,
            ]);

        return $products->concat($variants)->sortBy('stock')->values();
    }

    private function window(string $range): array
    {
        $to = now()->endOfDay();

        $from = match ($range) {
            '7d' => now()->subDays(6)->startOfDay(),
            '90d' => now()->subDays(89)->startOfDay(),
            'ytd' => now()->startOfYear(),
            default => now()->subDays(29)->startOfDay(),
        };

        return [$from, $to];
    }

    private function salesTrend(Carbon $from, Carbon $to): array
    {
        $pos = PosSale::where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as d, SUM(total) as amount')
            ->groupBy('d')->pluck('amount', 'd');

        $reseller = Payment::where('status', Payment::STATUS_PAID)
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('DATE(paid_at) as d, SUM(amount) as amount')
            ->groupBy('d')->pluck('amount', 'd');

        $consignment = ConsignmentSettlement::whereBetween('settled_on', [$from, $to])
            ->selectRaw('DATE(settled_on) as d, SUM(amount_collected) as amount')
            ->groupBy('d')->pluck('amount', 'd');

        $days = [];
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $key = $day->format('Y-m-d');
            $days[] = [
                'date' => $day->format('M j'),
                'pos' => round((float) ($pos[$key] ?? 0), 2),
                'reseller' => round((float) ($reseller[$key] ?? 0), 2),
                'consignment' => round((float) ($consignment[$key] ?? 0), 2),
            ];
        }

        return $days;
    }

    private function topProducts(Carbon $from, Carbon $to): array
    {
        $reseller = DB::table('reseller_order_items')
            ->join('reseller_orders', 'reseller_orders.id', '=', 'reseller_order_items.reseller_order_id')
            ->whereBetween('reseller_orders.created_at', [$from, $to])
            ->where('reseller_orders.status', '!=', ResellerOrder::STATUS_CANCELLED)
            ->groupBy('reseller_order_items.product_name')
            ->select('reseller_order_items.product_name as name', DB::raw('SUM(reseller_order_items.quantity) as qty'))
            ->pluck('qty', 'name');

        $pos = DB::table('pos_sale_items')
            ->join('pos_sales', 'pos_sales.id', '=', 'pos_sale_items.pos_sale_id')
            ->whereBetween('pos_sales.created_at', [$from, $to])
            ->where('pos_sales.status', 'completed')
            ->groupBy('pos_sale_items.product_name')
            ->select('pos_sale_items.product_name as name', DB::raw('SUM(pos_sale_items.quantity) as qty'))
            ->pluck('qty', 'name');

        return collect($reseller)->mergeRecursive($pos)
            ->map(fn ($v) => is_array($v) ? array_sum($v) : $v)
            ->sortDesc()
            ->take(6)
            ->map(fn ($qty, $name) => ['name' => $name, 'qty' => (int) $qty])
            ->values()
            ->all();
    }
}
