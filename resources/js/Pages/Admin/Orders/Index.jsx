import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import clsx from 'clsx';
import { Receipt, Search, Truck, Store } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import {
    Card,
    EmptyState,
    Input,
    Money,
    OrderStatusBadge,
    Pagination,
    PaymentStatusBadge,
    Select,
} from '@/Components/Lileu/ui';

const STATUSES = ['pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled'];
const PAYMENT_STATUSES = [
    ['unpaid', 'Unpaid'],
    ['partially_paid', 'Partially paid'],
    ['downpayment_paid', 'Downpayment paid'],
    ['fully_paid', 'Fully paid'],
    ['void', 'Void'],
];

export default function Index({ orders, filters, summary }) {
    const [term, setTerm] = useState(filters.q ?? '');

    const apply = (next) =>
        router.get(route('admin.orders.index'), { ...filters, ...next }, { preserveState: true, replace: true });

    return (
        <AdminLayout title="Reseller orders" subtitle="Every wholesale order and what it still owes.">
            <div className="mb-5 grid gap-4 sm:grid-cols-2">
                <Card className="card-pad flex items-center justify-between">
                    <div>
                        <p className="text-sm text-chocolate-400">Open orders</p>
                        <p className="font-display text-2xl font-semibold text-chocolate-700">{summary.open}</p>
                    </div>
                    <Receipt className="h-8 w-8 text-chocolate-200" />
                </Card>
                <Card className="card-pad flex items-center justify-between">
                    <div>
                        <p className="text-sm text-chocolate-400">Outstanding receivables</p>
                        <p className="font-display text-2xl font-semibold text-caramel-dark">
                            <Money value={summary.receivables} />
                        </p>
                    </div>
                </Card>
            </div>

            <Card className="card-pad">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        apply({ q: term || undefined });
                    }}
                    className="mb-5 flex flex-wrap items-center gap-3"
                >
                    <div className="relative min-w-52 flex-1">
                        <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-chocolate-300" />
                        <Input
                            value={term}
                            onChange={(e) => setTerm(e.target.value)}
                            placeholder="Order number or reseller…"
                            className="pl-10"
                        />
                    </div>

                    <Select
                        value={filters.status ?? ''}
                        onChange={(e) => apply({ status: e.target.value || undefined })}
                        className="w-auto min-w-40"
                    >
                        <option value="">All statuses</option>
                        {STATUSES.map((status) => (
                            <option key={status} value={status} className="capitalize">
                                {status}
                            </option>
                        ))}
                    </Select>

                    <Select
                        value={filters.payment_status ?? ''}
                        onChange={(e) => apply({ payment_status: e.target.value || undefined })}
                        className="w-auto min-w-44"
                    >
                        <option value="">All payment states</option>
                        {PAYMENT_STATUSES.map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </Select>
                </form>

                {orders.data.length === 0 ? (
                    <EmptyState
                        icon={Receipt}
                        title="No orders match that"
                        description="Try clearing the filters or searching a different order number."
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="table-lileu min-w-full">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Reseller</th>
                                    <th>Needed</th>
                                    <th>Status</th>
                                    <th className="text-right">Total</th>
                                    <th className="text-right">Paid</th>
                                    <th className="text-right">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                {orders.data.map((order) => {
                                    const Fulfillment = order.fulfillment_type === 'delivery' ? Truck : Store;

                                    return (
                                        <tr key={order.number} className="transition hover:bg-cream-50">
                                            <td>
                                                <Link
                                                    href={route('admin.orders.show', order.number)}
                                                    className="font-mono text-xs font-semibold text-chocolate-700 hover:underline"
                                                >
                                                    {order.number}
                                                </Link>
                                                <p className="mt-0.5 flex items-center gap-1 text-[11px] text-chocolate-300">
                                                    <Fulfillment className="h-3 w-3" />
                                                    {order.items_count} items · {order.placed_on}
                                                </p>
                                            </td>
                                            <td>
                                                <p className="max-w-44 truncate font-medium text-chocolate-700">
                                                    {order.reseller}
                                                </p>
                                                <p className="font-mono text-[11px] text-chocolate-300">
                                                    {order.reseller_code}
                                                </p>
                                            </td>
                                            <td className="whitespace-nowrap text-xs">{order.date_needed ?? '—'}</td>
                                            <td>
                                                <div className="flex flex-wrap gap-1.5">
                                                    <OrderStatusBadge status={order.status} />
                                                    <PaymentStatusBadge status={order.payment_status} />
                                                </div>
                                            </td>
                                            <td className="text-right font-semibold tabular-nums text-chocolate-700">
                                                <Money value={order.total} />
                                            </td>
                                            <td className="text-right tabular-nums text-success">
                                                <Money value={order.amount_paid} />
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
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination links={orders.links} className="mt-6" />
            </Card>
        </AdminLayout>
    );
}
