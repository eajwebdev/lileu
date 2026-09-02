import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import clsx from 'clsx';
import { CreditCard, Download, FileText, Printer, Search } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import {
    Card,
    EmptyState,
    Input,
    Money,
    Pagination,
    PaymentStatusBadge,
    Select,
} from '@/Components/Lileu/ui';

const STATUSES = ['pending', 'paid', 'failed', 'cancelled', 'refunded', 'void'];

export default function Index({ payments, filters, totals }) {
    const [term, setTerm] = useState(filters.q ?? '');

    const apply = (next) =>
        router.get(route('admin.payments.index'), { ...filters, ...next }, { preserveState: true, replace: true });

    return (
        <AdminLayout title="Payments" subtitle="Every receipt issued, and what is still pending.">
            <div className="mb-5 grid gap-4 sm:grid-cols-2">
                <Card className="card-pad">
                    <p className="text-sm text-chocolate-400">Collected all-time</p>
                    <p className="font-display text-2xl font-semibold text-success">
                        <Money value={totals.collected} />
                    </p>
                </Card>
                <Card className="card-pad">
                    <p className="text-sm text-chocolate-400">Awaiting settlement</p>
                    <p className="font-display text-2xl font-semibold text-caramel-dark">
                        <Money value={totals.pending} />
                    </p>
                </Card>
            </div>

            <Card className="card-pad">
                <div className="mb-5 flex flex-wrap items-center gap-3">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            apply({ q: term || undefined });
                        }}
                        className="relative min-w-52 flex-1"
                    >
                        <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-chocolate-300" />
                        <Input
                            value={term}
                            onChange={(e) => setTerm(e.target.value)}
                            placeholder="Receipt number or reference…"
                            className="pl-10"
                        />
                    </form>

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
                </div>

                {payments.data.length === 0 ? (
                    <EmptyState
                        icon={CreditCard}
                        title="No payments match that"
                        description="Receipts appear here as soon as a reseller pays or you record a manual payment."
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="table-lileu min-w-full">
                            <thead>
                                <tr>
                                    <th>Receipt</th>
                                    <th>Order</th>
                                    <th>Reseller</th>
                                    <th>Type</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                    <th className="text-right">Amount</th>
                                    <th className="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {payments.data.map((payment) => (
                                    <tr key={payment.id} className="transition hover:bg-cream-50">
                                        <td>
                                            <Link
                                                href={route('receipts.show', payment.receipt_number)}
                                                className="font-mono text-xs font-semibold text-chocolate-700 hover:underline"
                                            >
                                                {payment.receipt_number}
                                            </Link>
                                            <p className="text-[11px] text-chocolate-300">
                                                {payment.paid_on ?? `issued ${payment.created_on}`}
                                            </p>
                                        </td>
                                        <td>
                                            <Link
                                                href={route('admin.orders.show', payment.order_number)}
                                                className="font-mono text-xs text-chocolate-500 hover:underline"
                                            >
                                                {payment.order_number}
                                            </Link>
                                        </td>
                                        <td className="max-w-40 truncate text-xs">{payment.reseller}</td>
                                        <td className="text-xs">{payment.kind_label}</td>
                                        <td className="text-xs">{payment.method_label}</td>
                                        <td>
                                            <PaymentStatusBadge status={payment.status} />
                                        </td>
                                        <td
                                            className={clsx(
                                                'text-right font-semibold tabular-nums',
                                                payment.status === 'paid' ? 'text-success' : 'text-chocolate-500',
                                            )}
                                        >
                                            <Money value={payment.amount} />
                                        </td>
                                        <td>
                                            <div className="flex justify-end gap-1">
                                                <Link
                                                    href={route('receipts.show', payment.receipt_number)}
                                                    className="rounded-lg p-1.5 text-chocolate-400 transition hover:bg-cream-200 hover:text-chocolate-700"
                                                    title="View receipt"
                                                >
                                                    <FileText className="h-4 w-4" />
                                                </Link>
                                                <a
                                                    href={route('receipts.print', payment.receipt_number)}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="rounded-lg p-1.5 text-chocolate-400 transition hover:bg-cream-200 hover:text-chocolate-700"
                                                    title="Print"
                                                >
                                                    <Printer className="h-4 w-4" />
                                                </a>
                                                <a
                                                    href={route('receipts.pdf', payment.receipt_number)}
                                                    className="rounded-lg p-1.5 text-chocolate-400 transition hover:bg-cream-200 hover:text-chocolate-700"
                                                    title="Download PDF"
                                                >
                                                    <Download className="h-4 w-4" />
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination links={payments.links} className="mt-6" />
            </Card>
        </AdminLayout>
    );
}
