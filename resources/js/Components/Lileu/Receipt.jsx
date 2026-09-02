import clsx from 'clsx';
import { Money } from '@/Components/Lileu/ui';

/**
 * The Lil'Eu reseller receipt.
 *
 * Composition is fixed by the brand spec and mirrored 1:1 by the Blade/PDF
 * template so the on-screen receipt and the downloaded PDF match:
 *
 *   chocolate header → cream body → order info → item table
 *   → dark chocolate total bar → payment stamp → footer
 */

const STAMP_TONES = {
    green: 'border-success text-success',
    'muted-green': 'border-success/60 text-success/90',
    amber: 'border-caramel-dark/70 text-caramel-dark',
    'muted-red': 'border-cherry/60 text-cherry-dark',
};

function Stamp({ stamp, className }) {
    return (
        <span
            className={clsx(
                'inline-block -rotate-6 rounded-xl border-[3px] px-5 py-2.5 font-display text-lg font-bold uppercase tracking-[0.08em]',
                STAMP_TONES[stamp.tone] ?? STAMP_TONES.amber,
                className,
            )}
        >
            {stamp.label}
        </span>
    );
}

function InfoPair({ label, value, mono }) {
    if (!value) return null;

    return (
        <div>
            <dt className="text-[10px] font-semibold uppercase tracking-[0.14em] text-chocolate-400">{label}</dt>
            <dd className={clsx('mt-0.5 text-sm font-medium text-chocolate-700', mono && 'font-mono')}>{value}</dd>
        </div>
    );
}

export default function Receipt({ data, variant = 'payment' }) {
    const { brand, options, order, reseller, items, totals, payments, receipt = {}, stamp } = data;
    const isSummary = variant === 'summary';

    return (
        <article className="mx-auto w-full max-w-3xl overflow-hidden rounded-2xl border border-cream-300 bg-cream-100 shadow-lift print:rounded-none print:border-0 print:shadow-none">
            {/* 1 — Chocolate header */}
            <header className="bg-chocolate-700 px-6 py-6 text-cream-100 sm:px-9 sm:py-7">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex items-center gap-3.5">
                        {options.show_logo && (
                            <img src={brand.logo_mark} alt="" className="h-12 w-12 shrink-0 rounded-xl" />
                        )}
                        <div className="leading-tight">
                            <p className="font-display text-xl font-semibold tracking-tight sm:text-2xl">
                                {brand.name}
                            </p>
                            <p className="text-xs uppercase tracking-[0.18em] text-blush-300">{brand.tagline}</p>
                            <p className="mt-2 text-[11px] font-semibold uppercase tracking-[0.22em] text-cream-200/70">
                                {isSummary ? 'Order Payment Summary' : 'Official Receipt'}
                            </p>
                        </div>
                    </div>

                    <div className="text-right leading-tight">
                        <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-blush-300">
                            {isSummary ? 'Order No.' : 'Receipt No.'}
                        </p>
                        <p className="font-mono text-base font-semibold sm:text-lg">
                            {isSummary ? order.number : receipt.number}
                        </p>
                        {!isSummary && (
                            <p className="mt-2 text-[11px] text-cream-200/60">
                                Order <span className="font-mono">{order.number}</span>
                            </p>
                        )}
                    </div>
                </div>
            </header>

            {/* 2 — Cream body */}
            <div className="bg-cream-100 px-6 py-6 sm:px-9 sm:py-8">
                {/* 3 — Customer / order information */}
                <dl className="grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-4">
                    <InfoPair label="Order No." value={order.number} mono />
                    <InfoPair label="Order Date" value={order.ordered_date} />
                    <InfoPair label="Date Needed" value={order.date_needed} />
                    <InfoPair label="Time" value={order.time_needed ?? order.ordered_time} />
                    <InfoPair label="Reseller" value={reseller.business_name || reseller.name} />
                    <InfoPair label="Contact" value={reseller.phone} />
                    <InfoPair
                        label="Fulfillment"
                        value={order.fulfillment_type === 'delivery' ? 'Delivery' : 'Pickup'}
                    />
                    {!isSummary && <InfoPair label="Payment" value={receipt.method_label} />}
                    {order.delivery_address && (
                        <div className="col-span-2 sm:col-span-4">
                            <InfoPair label="Deliver To" value={order.delivery_address} />
                        </div>
                    )}
                </dl>

                {/* 4 — Item table */}
                <div className="mt-7 overflow-x-auto">
                    <table className="w-full min-w-[26rem] text-left text-sm">
                        <thead>
                            <tr className="border-b-2 border-chocolate-700">
                                <th className="pb-2 text-[10px] font-bold uppercase tracking-[0.14em] text-chocolate-600">
                                    Item
                                </th>
                                <th className="pb-2 text-center text-[10px] font-bold uppercase tracking-[0.14em] text-chocolate-600">
                                    Qty
                                </th>
                                <th className="pb-2 text-right text-[10px] font-bold uppercase tracking-[0.14em] text-chocolate-600">
                                    Unit Price
                                </th>
                                <th className="pb-2 text-right text-[10px] font-bold uppercase tracking-[0.14em] text-chocolate-600">
                                    Total
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.map((item, i) => (
                                <tr key={i} className="border-b border-chocolate-200/50">
                                    <td className="py-2.5 font-medium text-chocolate-700">{item.name}</td>
                                    <td className="py-2.5 text-center tabular-nums text-chocolate-600">
                                        {item.quantity}
                                    </td>
                                    <td className="py-2.5 text-right tabular-nums text-chocolate-600">
                                        <Money value={item.unit_price} />
                                    </td>
                                    <td className="py-2.5 text-right font-semibold tabular-nums text-chocolate-700">
                                        <Money value={item.line_total} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* 5 — Payment breakdown */}
                <div className="mt-6 flex justify-end">
                    <dl className="w-full max-w-xs space-y-1.5 text-sm">
                        <div className="flex justify-between text-chocolate-500">
                            <dt>Subtotal</dt>
                            <dd className="tabular-nums">
                                <Money value={totals.subtotal} />
                            </dd>
                        </div>
                        <div className="flex justify-between text-chocolate-500">
                            <dt>Discount</dt>
                            <dd className="tabular-nums">
                                {totals.discount > 0 ? '−' : ''}
                                <Money value={totals.discount} />
                            </dd>
                        </div>
                        <div className="flex justify-between text-chocolate-500">
                            <dt>Delivery Fee</dt>
                            <dd className="tabular-nums">
                                <Money value={totals.delivery_fee} />
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            {/* 6 — Dark chocolate total bar */}
            <div className="flex items-center justify-between bg-chocolate-800 px-6 py-4 text-cream-100 sm:px-9 sm:py-5">
                <span className="text-xs font-bold uppercase tracking-[0.2em] text-blush-300">Order Total</span>
                <span className="font-display text-2xl font-semibold tabular-nums sm:text-3xl">
                    <Money value={totals.total} />
                </span>
            </div>

            <div className="bg-cream-100 px-6 py-6 sm:px-9 sm:py-7">
                {/* Reseller money detail — total, paid, still owed */}
                <dl className="space-y-2 text-sm">
                    {!isSummary && (
                        <div className="flex justify-between text-chocolate-500">
                            <dt>Required Downpayment – {totals.downpayment_percent}%</dt>
                            <dd className="tabular-nums">
                                <Money value={totals.downpayment_required} />
                            </dd>
                        </div>
                    )}
                    {!isSummary && (
                        <div className="flex justify-between font-semibold text-chocolate-700">
                            <dt>Amount Paid (this receipt)</dt>
                            <dd className="tabular-nums">
                                <Money value={receipt.amount} />
                            </dd>
                        </div>
                    )}
                    <div className="flex justify-between font-semibold text-success">
                        <dt>Total Paid on Order</dt>
                        <dd className="tabular-nums">
                            <Money value={totals.amount_paid} />
                        </dd>
                    </div>
                    <div
                        className={clsx(
                            'flex justify-between rounded-xl px-3 py-2.5 font-display text-base font-bold',
                            totals.balance > 0
                                ? 'bg-caramel-soft/25 text-caramel-dark'
                                : 'bg-success-light text-success',
                        )}
                    >
                        <dt>Remaining Balance</dt>
                        <dd className="tabular-nums">
                            <Money value={totals.balance} />
                        </dd>
                    </div>
                </dl>

                {/* Payment history */}
                {payments.length > 0 && (
                    <div className="mt-7">
                        <p className="mb-2 text-[10px] font-bold uppercase tracking-[0.18em] text-chocolate-500">
                            Payment History
                        </p>
                        <table className="w-full text-left text-xs">
                            <thead>
                                <tr className="border-b border-chocolate-200/60 text-[10px] uppercase tracking-wider text-chocolate-400">
                                    <th className="pb-1.5 font-semibold">Date</th>
                                    <th className="pb-1.5 font-semibold">Receipt</th>
                                    <th className="pb-1.5 font-semibold">Method</th>
                                    <th className="pb-1.5 font-semibold">Type</th>
                                    <th className="pb-1.5 text-right font-semibold">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                {payments.map((entry) => (
                                    <tr key={entry.receipt_number} className="border-b border-chocolate-200/35">
                                        <td className="py-2 text-chocolate-600">
                                            {entry.date}
                                            <span className="ml-1 text-chocolate-300">{entry.time}</span>
                                        </td>
                                        <td className="py-2 font-mono text-chocolate-600">{entry.receipt_number}</td>
                                        <td className="py-2 text-chocolate-600">{entry.method_label}</td>
                                        <td className="py-2 text-chocolate-600">{entry.kind_label}</td>
                                        <td className="py-2 text-right font-semibold tabular-nums text-chocolate-700">
                                            <Money value={entry.amount} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colSpan={4} className="pt-2 text-right font-semibold text-chocolate-600">
                                        Total Paid
                                    </td>
                                    <td className="pt-2 text-right font-display text-sm font-bold tabular-nums text-chocolate-700">
                                        <Money value={totals.amount_paid} />
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                )}

                {/* 7 — Payment status stamp */}
                <div className="mt-8 flex flex-wrap items-end justify-between gap-6">
                    <div className="text-xs leading-relaxed text-chocolate-400">
                        {!isSummary && receipt.reference && (
                            <p>
                                Payment reference: <span className="font-mono">{receipt.reference}</span>
                            </p>
                        )}
                        {!isSummary && receipt.paid_date && (
                            <p>
                                Paid on {receipt.paid_date} at {receipt.paid_time}
                            </p>
                        )}
                        {order.notes && <p className="mt-1.5 max-w-xs">Note: {order.notes}</p>}
                    </div>

                    <Stamp stamp={stamp} />
                </div>
            </div>

            {/* 8 — Footer */}
            <footer className="border-t border-cream-300 bg-cream-50 px-6 py-5 text-center sm:px-9">
                <p className="font-display text-base font-semibold text-chocolate-700">{options.footer}</p>
                <p className="mt-1.5 text-[11px] text-chocolate-400">
                    {[brand.facebook, brand.phone, brand.website].filter(Boolean).join('  ·  ')}
                </p>
            </footer>
        </article>
    );
}
