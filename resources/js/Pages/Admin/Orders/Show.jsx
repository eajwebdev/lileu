import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import clsx from 'clsx';
import {
    ArrowLeft,
    Ban,
    Download,
    FileText,
    Mail,
    Phone,
    Plus,
    Printer,
    Store,
    Truck,
} from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import {
    Button,
    ButtonLink,
    Card,
    Field,
    Input,
    Money,
    OrderStatusBadge,
    PaymentStatusBadge,
    Select,
    Textarea,
} from '@/Components/Lileu/ui';

const STATUSES = ['pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled'];
const METHODS = [
    ['cash', 'Cash'],
    ['bank_transfer', 'Bank transfer'],
    ['gcash', 'GCash'],
    ['qrph', 'QR Ph'],
    ['manual', 'Other / manual'],
];

export default function Show({ order, reseller }) {
    const [showPaymentForm, setShowPaymentForm] = useState(false);
    const Fulfillment = order.fulfillment_type === 'delivery' ? Truck : Store;

    const statusForm = useForm({
        status: order.status,
        admin_notes: order.admin_notes ?? '',
        message: '',
    });

    const paymentForm = useForm({
        amount: order.balance,
        method: 'cash',
        reference: '',
    });

    const voidForm = useForm({});

    return (
        <AdminLayout
            title={order.number}
            subtitle={`Placed ${order.placed_on}`}
            action={
                <Link
                    href={route('admin.orders.index')}
                    className="inline-flex items-center gap-1.5 text-sm font-medium text-chocolate-400 transition hover:text-chocolate-700"
                >
                    <ArrowLeft className="h-4 w-4" /> All orders
                </Link>
            }
        >
            <div className="grid gap-5 xl:grid-cols-[1.6fr_1fr] xl:items-start">
                <div className="space-y-5">
                    <Card className="card-pad">
                        <div className="flex flex-wrap items-center gap-2">
                            <OrderStatusBadge status={order.status} />
                            <PaymentStatusBadge status={order.payment_status} />
                            <span className="badge bg-cream-200 text-chocolate-600">
                                <Fulfillment className="h-3.5 w-3.5" />
                                {order.fulfillment_type === 'delivery' ? 'Delivery' : 'Pickup'}
                            </span>
                        </div>

                        <dl className="mt-5 grid gap-4 border-t border-cream-200 pt-5 sm:grid-cols-3">
                            <div>
                                <dt className="text-xs font-semibold uppercase tracking-[0.14em] text-chocolate-400">
                                    Date needed
                                </dt>
                                <dd className="mt-0.5 text-sm font-medium text-chocolate-700">
                                    {order.date_needed ?? 'Not specified'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold uppercase tracking-[0.14em] text-chocolate-400">
                                    Downpayment
                                </dt>
                                <dd className="mt-0.5 text-sm font-medium text-chocolate-700">
                                    {order.downpayment_percent}% · <Money value={order.downpayment_required} />
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold uppercase tracking-[0.14em] text-chocolate-400">
                                    Placed
                                </dt>
                                <dd className="mt-0.5 text-sm font-medium text-chocolate-700">{order.placed_on}</dd>
                            </div>
                            {order.delivery_address && (
                                <div className="sm:col-span-3">
                                    <dt className="text-xs font-semibold uppercase tracking-[0.14em] text-chocolate-400">
                                        Delivery address
                                    </dt>
                                    <dd className="mt-0.5 text-sm text-chocolate-600">{order.delivery_address}</dd>
                                </div>
                            )}
                            {order.notes && (
                                <div className="sm:col-span-3">
                                    <dt className="text-xs font-semibold uppercase tracking-[0.14em] text-chocolate-400">
                                        Reseller notes
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
                                        <th>SKU</th>
                                        <th className="text-center">Qty</th>
                                        <th className="text-right">Unit price</th>
                                        <th className="text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {order.items.map((item, i) => (
                                        <tr key={i}>
                                            <td className="font-medium text-chocolate-700">{item.name}</td>
                                            <td className="font-mono text-xs text-chocolate-300">{item.sku}</td>
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

                    {/* Payments */}
                    <Card className="card-pad">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <h2 className="font-display text-lg font-semibold text-chocolate-700">
                                Payments & receipts
                            </h2>
                            <div className="flex gap-2">
                                <ButtonLink
                                    href={route('receipts.summary', order.number)}
                                    variant="ghost"
                                    className="px-3 py-1.5 text-xs"
                                >
                                    <FileText className="h-3.5 w-3.5" /> Summary
                                </ButtonLink>
                                {order.balance > 0 && (
                                    <Button
                                        variant="secondary"
                                        onClick={() => setShowPaymentForm((v) => !v)}
                                        className="px-3 py-1.5 text-xs"
                                    >
                                        <Plus className="h-3.5 w-3.5" /> Record payment
                                    </Button>
                                )}
                            </div>
                        </div>

                        {showPaymentForm && (
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    paymentForm.post(route('admin.orders.payments.store', order.number), {
                                        preserveScroll: true,
                                        onSuccess: () => setShowPaymentForm(false),
                                    });
                                }}
                                className="mt-4 grid gap-3 rounded-2xl border border-blush-200 bg-blush-50 p-4 sm:grid-cols-4"
                            >
                                <Field label="Amount" error={paymentForm.errors.amount}>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        max={order.balance}
                                        value={paymentForm.data.amount}
                                        onChange={(e) => paymentForm.setData('amount', e.target.value)}
                                    />
                                </Field>
                                <Field label="Method" error={paymentForm.errors.method}>
                                    <Select
                                        value={paymentForm.data.method}
                                        onChange={(e) => paymentForm.setData('method', e.target.value)}
                                    >
                                        {METHODS.map(([value, label]) => (
                                            <option key={value} value={value}>
                                                {label}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                                <Field label="Reference" error={paymentForm.errors.reference}>
                                    <Input
                                        value={paymentForm.data.reference}
                                        onChange={(e) => paymentForm.setData('reference', e.target.value)}
                                        placeholder="OR number, transfer ref…"
                                    />
                                </Field>
                                <div className="flex items-end">
                                    <Button type="submit" disabled={paymentForm.processing} className="w-full">
                                        Record
                                    </Button>
                                </div>
                                <p className="text-xs text-chocolate-400 sm:col-span-4">
                                    This issues a new receipt number and updates the balance. It does not re-post an
                                    existing payment.
                                </p>
                            </form>
                        )}

                        {order.payments.length === 0 ? (
                            <p className="mt-4 rounded-xl border border-dashed border-cream-300 px-4 py-6 text-center text-sm text-chocolate-400">
                                No payments recorded yet.
                            </p>
                        ) : (
                            <div className="mt-4 overflow-x-auto">
                                <table className="table-lileu min-w-full">
                                    <thead>
                                        <tr>
                                            <th>Receipt</th>
                                            <th>Type</th>
                                            <th>Method</th>
                                            <th>Status</th>
                                            <th className="text-right">Amount</th>
                                            <th className="text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {order.payments.map((payment) => (
                                            <tr key={payment.id}>
                                                <td>
                                                    <span className="font-mono text-xs font-semibold text-chocolate-700">
                                                        {payment.receipt_number}
                                                    </span>
                                                    {payment.paid_on && (
                                                        <p className="text-[11px] text-chocolate-300">
                                                            {payment.paid_on}
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="text-xs">{payment.kind_label}</td>
                                                <td className="text-xs">{payment.method_label}</td>
                                                <td>
                                                    <PaymentStatusBadge status={payment.status} />
                                                </td>
                                                <td className="text-right font-semibold tabular-nums text-chocolate-700">
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
                                                            title="Print receipt"
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
                                                        {payment.status === 'paid' && (
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    voidForm.post(
                                                                        route('admin.payments.void', payment.id),
                                                                        { preserveScroll: true },
                                                                    )
                                                                }
                                                                className="rounded-lg p-1.5 text-chocolate-400 transition hover:bg-cherry/10 hover:text-cherry"
                                                                title="Void this receipt"
                                                            >
                                                                <Ban className="h-4 w-4" />
                                                            </button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Card>
                </div>

                {/* Side panel */}
                <div className="space-y-4 xl:sticky xl:top-6">
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
                            <div className="flex justify-between border-t border-cream-200 pt-2.5 font-semibold text-success">
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
                                <dt>Balance</dt>
                                <dd className="tabular-nums">
                                    <Money value={order.balance} />
                                </dd>
                            </div>
                        </dl>
                    </Card>

                    <Card className="card-pad">
                        <h2 className="font-display text-base font-semibold text-chocolate-700">Reseller</h2>
                        <p className="mt-2 font-medium text-chocolate-700">
                            {reseller.business_name || reseller.name}
                        </p>
                        <p className="font-mono text-xs text-chocolate-300">{reseller.code}</p>

                        <div className="mt-3 space-y-1.5 text-sm text-chocolate-500">
                            <p className="flex items-center gap-2">
                                <Phone className="h-4 w-4 text-blush-500" /> {reseller.phone}
                            </p>
                            <p className="flex items-center gap-2">
                                <Mail className="h-4 w-4 text-blush-500" /> {reseller.email}
                            </p>
                        </div>

                        <div className="mt-4 grid grid-cols-2 gap-2">
                            <ButtonLink
                                href={route('admin.resellers.show', reseller.id)}
                                variant="ghost"
                                className="text-xs"
                            >
                                Profile
                            </ButtonLink>
                            <ButtonLink
                                href={route('admin.messages.index', { reseller: reseller.id })}
                                variant="secondary"
                                className="text-xs"
                            >
                                Message
                            </ButtonLink>
                        </div>
                    </Card>

                    <Card className="card-pad">
                        <h2 className="font-display text-base font-semibold text-chocolate-700">Update status</h2>

                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                statusForm.post(route('admin.orders.status', order.number), {
                                    preserveScroll: true,
                                });
                            }}
                            className="mt-3 space-y-3"
                        >
                            <Field label="Order status" error={statusForm.errors.status}>
                                <Select
                                    value={statusForm.data.status}
                                    onChange={(e) => statusForm.setData('status', e.target.value)}
                                >
                                    {STATUSES.map((status) => (
                                        <option key={status} value={status} className="capitalize">
                                            {status}
                                        </option>
                                    ))}
                                </Select>
                            </Field>

                            <Field label="Internal notes" error={statusForm.errors.admin_notes}>
                                <Textarea
                                    rows={2}
                                    value={statusForm.data.admin_notes}
                                    onChange={(e) => statusForm.setData('admin_notes', e.target.value)}
                                    placeholder="Only visible to your team"
                                />
                            </Field>

                            <Field
                                label="Message to the reseller"
                                hint="Optional — sent to their chat thread."
                                error={statusForm.errors.message}
                            >
                                <Textarea
                                    rows={2}
                                    value={statusForm.data.message}
                                    onChange={(e) => statusForm.setData('message', e.target.value)}
                                    placeholder="Your order is ready for pickup!"
                                />
                            </Field>

                            <Button type="submit" disabled={statusForm.processing} className="w-full">
                                Save status
                            </Button>
                        </form>
                    </Card>
                </div>
            </div>
        </AdminLayout>
    );
}
