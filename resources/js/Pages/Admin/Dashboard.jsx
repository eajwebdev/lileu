import { Link, router } from '@inertiajs/react';
import clsx from 'clsx';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import {
    AlertTriangle,
    ArrowRight,
    Banknote,
    HandCoins,
    PiggyBank,
    Receipt,
    ShoppingCart,
    TrendingUp,
    UserPlus,
    Wallet,
} from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Card, Money, OrderStatusBadge, PaymentStatusBadge, peso } from '@/Components/Lileu/ui';

const RANGES = [
    ['7d', '7 days'],
    ['30d', '30 days'],
    ['90d', '90 days'],
    ['ytd', 'Year to date'],
];

/* One restrained accent per KPI — never a rainbow of bright cards. */
const KPI_TONES = {
    sales: 'bg-chocolate-100 text-chocolate-700',
    purchases: 'bg-caramel-soft/30 text-caramel-dark',
    expenses: 'bg-blush-100 text-blush-600',
    profit: 'bg-success-light text-success',
    commissions: 'bg-blush-100 text-blush-600',
};

function Kpi({ icon: Icon, label, value, tone, hint }) {
    return (
        <Card className="card-pad">
            <div className="flex items-start justify-between gap-3">
                <span className={clsx('flex h-11 w-11 items-center justify-center rounded-xl', tone)}>
                    <Icon className="h-5 w-5" />
                </span>
            </div>
            <p className="mt-4 font-display text-2xl font-semibold text-chocolate-700">
                <Money value={value} decimals={0} />
            </p>
            <p className="mt-0.5 text-sm font-medium text-chocolate-400">{label}</p>
            {hint && <p className="mt-1 text-xs text-chocolate-300">{hint}</p>}
        </Card>
    );
}

function ChartTooltip({ active, payload, label }) {
    if (!active || !payload?.length) return null;

    return (
        <div className="rounded-xl border border-cream-300 bg-vanilla px-3 py-2 shadow-lift">
            <p className="text-xs font-semibold text-chocolate-700">{label}</p>
            {payload.map((entry) => (
                <p key={entry.dataKey} className="text-xs text-chocolate-500">
                    <span
                        className="mr-1.5 inline-block h-2 w-2 rounded-full align-middle"
                        style={{ background: entry.color }}
                    />
                    {entry.name}: <span className="font-semibold">{peso(entry.value)}</span>
                </p>
            ))}
        </div>
    );
}

export default function Dashboard({ range, kpis, counters, salesTrend, topProducts, recentOrders, lowStock }) {
    return (
        <AdminLayout
            title="Dashboard"
            subtitle="How the shop is doing right now."
            action={
                <div className="flex flex-wrap gap-1.5 rounded-xl border border-cream-300 bg-vanilla p-1">
                    {RANGES.map(([key, label]) => (
                        <button
                            key={key}
                            type="button"
                            onClick={() =>
                                router.get(route('admin.dashboard'), { range: key }, { preserveState: true })
                            }
                            className={clsx(
                                'rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                                range === key
                                    ? 'bg-chocolate-700 text-cream-100'
                                    : 'text-chocolate-500 hover:bg-cream-200',
                            )}
                        >
                            {label}
                        </button>
                    ))}
                </div>
            }
        >
            {/* Attention strip */}
            {(counters.pending_applications > 0 || counters.low_stock > 0) && (
                <div className="mb-5 flex flex-wrap gap-3">
                    {counters.pending_applications > 0 && (
                        <Link
                            href={route('admin.resellers.index', { status: 'pending' })}
                            className="flex flex-1 items-center gap-3 rounded-2xl border border-blush-200 bg-blush-50 px-4 py-3 transition hover:shadow-soft"
                        >
                            <UserPlus className="h-5 w-5 shrink-0 text-blush-600" />
                            <span className="flex-1 text-sm font-medium text-chocolate-700">
                                {counters.pending_applications} reseller application
                                {counters.pending_applications === 1 ? '' : 's'} waiting for review
                            </span>
                            <ArrowRight className="h-4 w-4 text-chocolate-400" />
                        </Link>
                    )}
                    {counters.low_stock > 0 && (
                        <Link
                            href={route('admin.products.index')}
                            className="flex flex-1 items-center gap-3 rounded-2xl border border-caramel/30 bg-caramel-soft/15 px-4 py-3 transition hover:shadow-soft"
                        >
                            <AlertTriangle className="h-5 w-5 shrink-0 text-caramel-dark" />
                            <span className="flex-1 text-sm font-medium text-chocolate-700">
                                {counters.low_stock} product{counters.low_stock === 1 ? '' : 's'} running low on stock
                            </span>
                            <ArrowRight className="h-4 w-4 text-chocolate-400" />
                        </Link>
                    )}
                </div>
            )}

            {/* KPIs */}
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <Kpi
                    icon={TrendingUp}
                    label="Sales"
                    value={kpis.sales}
                    tone={KPI_TONES.sales}
                    hint={`POS ${peso(kpis.pos_sales)} · Reseller ${peso(kpis.reseller_collected)} · Consignment ${peso(kpis.consignment_collected)}`}
                />
                <Kpi icon={ShoppingCart} label="Purchases" value={kpis.purchases} tone={KPI_TONES.purchases} />
                <Kpi icon={Banknote} label="Expenses" value={kpis.expenses} tone={KPI_TONES.expenses} />
                <Kpi icon={PiggyBank} label="Net profit" value={kpis.net_profit} tone={KPI_TONES.profit} />
                <Kpi
                    icon={HandCoins}
                    label="Reseller commissions"
                    value={kpis.commissions}
                    tone={KPI_TONES.commissions}
                    hint="Margin given to the channel"
                />
            </div>

            {/* Counters */}
            <div className="mt-4 grid gap-4 sm:grid-cols-3">
                <Card className="card-pad flex items-center gap-4">
                    <Receipt className="h-8 w-8 text-chocolate-300" />
                    <div>
                        <p className="font-display text-2xl font-semibold text-chocolate-700">{counters.open_orders}</p>
                        <p className="text-sm text-chocolate-400">Open reseller orders</p>
                    </div>
                </Card>
                <Card className="card-pad flex items-center gap-4">
                    <Wallet className="h-8 w-8 text-caramel" />
                    <div>
                        <p className="font-display text-2xl font-semibold text-chocolate-700">
                            {counters.awaiting_payment}
                        </p>
                        <p className="text-sm text-chocolate-400">Orders awaiting payment</p>
                    </div>
                </Card>
                <Card className="card-pad flex items-center gap-4">
                    <HandCoins className="h-8 w-8 text-caramel-dark" />
                    <div>
                        <p className="font-display text-2xl font-semibold text-caramel-dark">
                            <Money value={counters.receivables} decimals={0} />
                        </p>
                        <p className="text-sm text-chocolate-400">Total receivables</p>
                    </div>
                </Card>
            </div>

            {/* Charts */}
            <div className="mt-6 grid gap-4 lg:grid-cols-[1.6fr_1fr]">
                <Card className="card-pad">
                    <h2 className="font-display text-lg font-semibold text-chocolate-700">Sales trend</h2>
                    <p className="text-sm text-chocolate-400">
                        Counter sales, reseller payments and consignment collections.
                    </p>

                    <div className="mt-5 h-64">
                        <ResponsiveContainer width="100%" height="100%">
                            <AreaChart data={salesTrend} margin={{ top: 5, right: 5, left: -18, bottom: 0 }}>
                                <defs>
                                    <linearGradient id="posFill" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stopColor="#3B2A22" stopOpacity={0.28} />
                                        <stop offset="100%" stopColor="#3B2A22" stopOpacity={0.02} />
                                    </linearGradient>
                                    <linearGradient id="resellerFill" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stopColor="#EFAFB8" stopOpacity={0.5} />
                                        <stop offset="100%" stopColor="#EFAFB8" stopOpacity={0.04} />
                                    </linearGradient>
                                    <linearGradient id="consignmentFill" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stopColor="#C98B4B" stopOpacity={0.45} />
                                        <stop offset="100%" stopColor="#C98B4B" stopOpacity={0.03} />
                                    </linearGradient>
                                </defs>
                                <CartesianGrid stroke="#EBD8C3" strokeDasharray="3 3" vertical={false} />
                                <XAxis
                                    dataKey="date"
                                    tick={{ fontSize: 11, fill: '#8A6B5A' }}
                                    tickLine={false}
                                    axisLine={false}
                                    minTickGap={24}
                                />
                                <YAxis
                                    tick={{ fontSize: 11, fill: '#8A6B5A' }}
                                    tickLine={false}
                                    axisLine={false}
                                    width={56}
                                />
                                <Tooltip content={<ChartTooltip />} />
                                <Area
                                    type="monotone"
                                    dataKey="reseller"
                                    name="Reseller"
                                    stroke="#EFAFB8"
                                    strokeWidth={2}
                                    fill="url(#resellerFill)"
                                />
                                <Area
                                    type="monotone"
                                    dataKey="consignment"
                                    name="Consignment"
                                    stroke="#C98B4B"
                                    strokeWidth={2}
                                    fill="url(#consignmentFill)"
                                />
                                <Area
                                    type="monotone"
                                    dataKey="pos"
                                    name="POS"
                                    stroke="#3B2A22"
                                    strokeWidth={2}
                                    fill="url(#posFill)"
                                />
                            </AreaChart>
                        </ResponsiveContainer>
                    </div>
                </Card>

                <Card className="card-pad">
                    <h2 className="font-display text-lg font-semibold text-chocolate-700">Top sellers</h2>
                    <p className="text-sm text-chocolate-400">Units moved in this period.</p>

                    <div className="mt-5 h-64">
                        <ResponsiveContainer width="100%" height="100%">
                            <BarChart
                                data={topProducts}
                                layout="vertical"
                                margin={{ top: 0, right: 12, left: 0, bottom: 0 }}
                            >
                                <CartesianGrid stroke="#EBD8C3" strokeDasharray="3 3" horizontal={false} />
                                <XAxis type="number" tick={{ fontSize: 11, fill: '#8A6B5A' }} axisLine={false} tickLine={false} />
                                <YAxis
                                    type="category"
                                    dataKey="name"
                                    tick={{ fontSize: 11, fill: '#8A6B5A' }}
                                    axisLine={false}
                                    tickLine={false}
                                    width={110}
                                />
                                <Tooltip
                                    cursor={{ fill: '#FBF5EC' }}
                                    contentStyle={{
                                        borderRadius: 12,
                                        border: '1px solid #EBD8C3',
                                        background: '#FFFDFC',
                                        fontSize: 12,
                                    }}
                                />
                                <Bar dataKey="qty" name="Units" fill="#D7973E" radius={[0, 6, 6, 0]} barSize={14} />
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                </Card>
            </div>

            {/* Lists */}
            <div className="mt-6 grid gap-4 lg:grid-cols-[1.6fr_1fr]">
                <Card className="card-pad">
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="font-display text-lg font-semibold text-chocolate-700">Recent orders</h2>
                        <Link
                            href={route('admin.orders.index')}
                            className="inline-flex items-center gap-1.5 text-sm font-semibold text-chocolate-500 transition hover:text-chocolate-700"
                        >
                            All orders <ArrowRight className="h-4 w-4" />
                        </Link>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="table-lileu min-w-full">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Reseller</th>
                                    <th>Status</th>
                                    <th className="text-right">Total</th>
                                    <th className="text-right">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recentOrders.map((order) => (
                                    <tr key={order.number}>
                                        <td>
                                            <Link
                                                href={route('admin.orders.show', order.number)}
                                                className="font-mono text-xs font-semibold text-chocolate-700 hover:underline"
                                            >
                                                {order.number}
                                            </Link>
                                            <span className="ml-2 text-xs text-chocolate-300">{order.placed_on}</span>
                                        </td>
                                        <td className="max-w-40 truncate">{order.reseller}</td>
                                        <td>
                                            <div className="flex flex-wrap gap-1.5">
                                                <OrderStatusBadge status={order.status} />
                                                <PaymentStatusBadge status={order.payment_status} />
                                            </div>
                                        </td>
                                        <td className="text-right font-semibold tabular-nums text-chocolate-700">
                                            <Money value={order.total} />
                                        </td>
                                        <td
                                            className={clsx(
                                                'text-right font-semibold tabular-nums',
                                                order.balance > 0 ? 'text-caramel-dark' : 'text-success',
                                            )}
                                        >
                                            <Money value={order.balance} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>

                <Card className="card-pad">
                    <h2 className="font-display text-lg font-semibold text-chocolate-700">Low stock</h2>
                    <p className="text-sm text-chocolate-400">Below the reorder threshold.</p>

                    {lowStock.length === 0 ? (
                        <p className="mt-6 rounded-xl bg-success-light px-4 py-6 text-center text-sm font-medium text-success">
                            Every product is comfortably stocked.
                        </p>
                    ) : (
                        <ul className="mt-4 divide-y divide-cream-200">
                            {lowStock.map((product) => (
                                <li key={product.id} className="flex items-center justify-between py-2.5">
                                    <span className="text-sm font-medium text-chocolate-700">{product.name}</span>
                                    <span className="badge bg-caramel-soft/30 text-caramel-dark">
                                        {product.stock} left
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>
        </AdminLayout>
    );
}
