import { Link, router } from '@inertiajs/react';
import clsx from 'clsx';
import {
    ArrowLeft,
    Download,
    FileText,
    MessageCircle,
    Printer,
    Store,
    Truck,
    Wallet,
} from 'lucide-react';
import PortalLayout from '@/Layouts/PortalLayout';
import {
    Button,
    ButtonLink,
    Card,
    Money,
    OrderStatusBadge,
    PaymentStatusBadge,
} from '@/Components/Lileu/ui';

const TIMELINE = [
    ['pending', 'Placed'],
    ['confirmed', 'Confirmed'],
    ['preparing', 'Preparing'],
    ['ready', 'Ready'],
    ['completed', 'Completed'],
];

function Timeline({ status }) {
    const index = TIMELINE.findIndex(([key]) => key === status);

    if (status === 'cancelled') {
        return (
            <div className="rounded-2xl border border-cherry/20 bg-cherry/5 px-4 py-3 text-sm font-medium text-cherry-dark">
                This order was cancelled.
            </div>
        );
    }

    return (
        <ol className="flex items-center gap-1.5">
            {TIMELINE.map(([key, label], i) => {
                const done = i <= index;

                return (
                    <li key={key} className="flex flex-1 flex-col gap-1.5">
                        <span
                            className={clsx(
                                'h-1.5 rounded-full transition',
                                done ? 'bg-chocolate-700' : 'bg-cream-300',
                            )}
                        />
                        <span
                            className={clsx(
                                'text-[10px] font-semibold uppercase tracking-wider',
                                done ? 'text-chocolate-600' : 'text-chocolate-300',
                            )}
                        >
                            {label}
                        </span>
                    </li>
                );
            })}
        </ol>
    );
}

export default function Show({ order }) {
    const Fulfillment = order.fulfillment_type === 'delivery' ? Truck : Store;
    const paidPayments = order.payments.filter((p) => p.status === 'paid');
    const latestReceipt = paidPayments[0]?.receipt_number;

    return (
        <PortalLayout
            title={order.number}
            subtitle={`Placed ${order.placed_on} at ${order.placed_time}`}
            action={
                <Link
                    href={route('portal.orders.index')}
                    className="inline-flex items-center gap-1.5 text-sm font-medium text-chocolate-400 transition hover:text-chocolate-700"
                >
                    <ArrowLeft className="h-4 w-4" /> All orders
                </Link>
            }
        >
            <div className="grid gap-5 lg:grid-cols-[1.5fr_1fr] lg:items-start">
                <div className="space-y-5">
                    <Card className="card-pad">
                        <div className="mb-5 flex flex-wrap items-center gap-2">
                            <OrderStatusBadge status={order.status} />
                            <PaymentStatusBadge status={order.payment_status} />
                            <span className="badge bg-cream-200 text-chocolate-600">
                                <Fulfillment className="h-3.5 w-3.5" />
                                {order.fulfillment_type === 'delivery' ? 'Delivery' : 'Pickup'}
                            </span>
                        </div>

                        <Timeline status={order.status} />

                        <dl className="mt-6 grid gap-4 border-t border-cream-200 pt-5 sm:grid-cols-2">
                            <div>
                                <dt className="text-xs font-semibold uppercase tracking-[0.14em] text-chocolate-400">
                                    Date needed
                                </dt>
                                <dd className="mt-0.5 text-sm font-medium text-chocolate-700">
                                    {order.date_needed ?? 'Not specified'}
                                </dd>
                            </div>
                            {order.fulfillment_type === 'delivery' && order.delivery_address && (
                                <div>
                                    <dt className="text-xs font-semibold uppercase tracking-[0.14em] text-chocolate-400">
                                        Delivery address
                                    </dt>
                                    <dd className="mt-0.5 text-sm font-medium text-chocolate-700">
                                        {order.delivery_address}
                                    </dd>
                                </div>
                            )}
                            {order.notes && (
                                <div className="sm:col-span-2">
                                    <dt className="text-xs font-semibold uppercase tracking-[0.14em] text-chocolate-400">
                                        Your notes
                                    </dt>
                                    <dd className="mt-0.5 text-sm text-chocolate-600">{order.notes}</dd>
                                </div>
                            )}
                        </dl>
                    </Card>

                    <Card className="card-pad">
                        <h2 className="font-display text-lg font-semibold text-chocolate-700">Items</h2>

                        <div className="mt-4 overflow-x-auto">
                            <table className="table-lileu min-w-full">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th className="text-center">Qty</th>
                                        <th className="text-right">Unit price</th>
                                        <th className="text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {order.items.map((item, i) => (
                                        <tr key={i}>
                                            <td className="font-medium text-chocolate-700">{item.name}</td>
                                            <td className="text-center tabular-nums">{item.quantity}</td>
                                            <td className="text-right tabular-nums">
                                                <Money value={item.unit_price} />
                                            </td>
                                            <td className="text-right font-semibold tabular-nums text-chocolate-700">
                                                <Money value={item.line_total} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Card>

                    {paidPayments.length > 0 && (
                        <Card className="card-pad">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <h2 className="font-display text-lg font-semibold text-chocolate-700">
                                    Payment history
                                </h2>
                                <Link
                                    href={route('receipts.summary', order.number)}
                                    className="inline-flex items-center gap-1.5 text-sm font-semibold text-chocolate-500 transition hover:text-chocolate-700"
                                >
                                    <FileText className="h-4 w-4" /> Payment summary
                                </Link>
                            </div>

                            <ul className="mt-4 divide-y divide-cream-200">
                                {paidPayments.map((payment) => (
                                    <li
                                        key={payment.receipt_number}
                                        className="flex flex-wrap items-center justify-between gap-3 py-3"
                                    >
                                        <div>
                                            <p className="font-mono text-sm font-semibold text-chocolate-700">
                                                {payment.receipt_number}
                                            </p>
                                            <p className="text-xs text-chocolate-400">
                                                {payment.kind_label} · {payment.method_label} · {payment.paid_on}
                                            </p>
                                        </div>

                                        <div className="flex items-center gap-2">
                                            <span className="font-semibold tabular-nums text-chocolate-700">
                                                <Money value={payment.amount} />
                                            </span>
                                            <Link
                                                href={route('receipts.show', payment.receipt_number)}
                                                className="btn-ghost px-3 py-1.5 text-xs"
                                            >
                                                View
                                            </Link>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </Card>
                    )}
                </div>

                {/* Money panel */}
                <div className="space-y-4 lg:sticky lg:top-24">
                    <Card className="overflow-hidden">
                        <div className="bg-choco-fade px-5 py-5 text-cream-100">
                            <p className="text-xs font-semibold uppercase tracking-[0.16em] text-blush-300">
                                Order total
                            </p>
                            <p className="mt-1 font-display text-3xl font-semibold">
                                <Money value={order.total} />
                            </p>
                        </div>

                        <dl className="space-y-2 p-5 text-sm">
                            <div className="flex justify-between text-chocolate-500">
                                <dt>Subtotal</dt>
                                <dd className="tabular-nums">
                                    <Money value={order.subtotal} />
                                </dd>
                            </div>
                            {order.discount > 0 && (
                                <div className="flex justify-between text-success">
                                    <dt>Discount</dt>
                                    <dd className="tabular-nums">
                                        −<Money value={order.discount} />
                                    </dd>
                                </div>
                            )}
                            {order.delivery_fee > 0 && (
                                <div className="flex justify-between text-chocolate-500">
                                    <dt>Delivery fee</dt>
                                    <dd className="tabular-nums">
                                        <Money value={order.delivery_fee} />
                                    </dd>
                                </div>
                            )}

                            <div className="flex justify-between border-t border-cream-200 pt-2.5 text-chocolate-500">
                                <dt>Required downpayment ({order.downpayment_percent}%)</dt>
                                <dd className="tabular-nums">
                                    <Money value={order.downpayment_required} />
                                </dd>
                            </div>
                            <div className="flex justify-between font-semibold text-success">
                                <dt>Amount paid</dt>
                                <dd className="tabular-nums">
                                    <Money value={order.amount_paid} />
                                </dd>
                            </div>
                            <div
                                className={clsx(
                                    'flex justify-between rounded-xl px-3 py-2.5 font-display text-base font-semibold',
                                    order.balance > 0
                                        ? 'bg-caramel-soft/25 text-caramel-dark'
                                        : 'bg-success-light text-success',
                                )}
                            >
                                <dt>Remaining balance</dt>
                                <dd className="tabular-nums">
                                    <Money value={order.balance} />
                                </dd>
                            </div>
                        </dl>
                    </Card>

                    <div className="space-y-2">
                        {order.balance > 0 && order.status !== 'cancelled' && (
                            <Button
                                onClick={() => router.post(route('portal.orders.pay', order.number))}
                                className="w-full py-3 text-base"
                            >
                                <Wallet className="h-4 w-4" />
                                Pay <Money value={order.due_now} />
                            </Button>
                        )}

                        {latestReceipt && (
                            <div className="grid grid-cols-3 gap-2">
                                <ButtonLink
                                    href={route('receipts.show', latestReceipt)}
                                    variant="ghost"
                                    className="flex-col gap-1 py-3 text-xs"
                                >
                                    <FileText className="h-4 w-4" />
                                    View
                                </ButtonLink>
                                <a
                                    href={route('receipts.print', latestReceipt)}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="btn-ghost flex-col gap-1 py-3 text-xs"
                                >
                                    <Printer className="h-4 w-4" />
                                    Print
                                </a>
                                <a
                                    href={route('receipts.pdf', latestReceipt)}
                                    className="btn-ghost flex-col gap-1 py-3 text-xs"
                                >
                                    <Download className="h-4 w-4" />
                                    PDF
                                </a>
                            </div>
                        )}

                        <ButtonLink
                            href={route('portal.messages.index')}
                            variant="secondary"
                            className="w-full py-2.5"
                        >
                            <MessageCircle className="h-4 w-4" />
                            Message Lil&rsquo;Eu
                        </ButtonLink>
                    </div>
                </div>
            </div>
        </PortalLayout>
    );
}
