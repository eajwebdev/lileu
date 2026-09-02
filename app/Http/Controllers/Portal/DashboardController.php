<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\ResellerOrder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $reseller = $request->user()->reseller;

        $orders = $reseller->orders()->with('items')->latest()->get();

        return Inertia::render('Portal/Dashboard', [
            // Deliberately operational, not analytical: what to pay, what is
            // being made, what is ready, and what is still owed.
            'cards' => [
                'approved_products' => $reseller->products()->wherePivot('is_approved', true)->count(),
                'current_orders' => $orders->whereNotIn('status', [
                    ResellerOrder::STATUS_COMPLETED,
                    ResellerOrder::STATUS_CANCELLED,
                ])->count(),
                'to_pay' => $orders->where('balance', '>', 0)
                    ->whereNotIn('status', [ResellerOrder::STATUS_CANCELLED])->count(),
                'preparing' => $orders->where('status', ResellerOrder::STATUS_PREPARING)->count(),
                'ready' => $orders->where('status', ResellerOrder::STATUS_READY)->count(),
                'remaining_balance' => round((float) $orders
                    ->whereNotIn('status', [ResellerOrder::STATUS_CANCELLED])
                    ->sum('balance'), 2),
                'unread_messages' => Message::where('reseller_id', $reseller->id)
                    ->where('author_role', 'admin')
                    ->whereNull('read_at')
                    ->count(),
            ],
            'recentOrders' => $orders->take(6)->map(fn (ResellerOrder $order) => [
                'number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'total' => (float) $order->total,
                'balance' => (float) $order->balance,
                'item_count' => $order->items->sum('quantity'),
                'placed_on' => $order->created_at->format('M j, Y'),
                'date_needed' => $order->date_needed?->format('M j, Y'),
            ])->values(),
        ]);
    }
}
