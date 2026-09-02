import { Head, usePage } from '@inertiajs/react';
import clsx from 'clsx';
import { CheckCircle2, FileText, MessageCircle, Receipt } from 'lucide-react';
import { ButtonLink, FlashToasts, Logo, Money } from '@/Components/Lileu/ui';

export default function Success({ payment, order, resellerFirstName }) {
    const { brand } = usePage().props;
    const settled = order.is_fully_paid;

    return (
        <div className="min-h-screen bg-cream-fade">
            <Head title="Payment received" />
            <FlashToasts />

            <div className="mx-auto max-w-2xl px-4 py-12 sm:py-20">
                <div className="mb-8 flex items-center justify-center gap-2.5">
                    <Logo className="h-10 w-10" />
                    <span className="font-display text-lg font-semibold text-chocolate-700">{brand?.name}</span>
                </div>

                <div className="overflow-hidden rounded-3xl border border-cream-300 bg-vanilla shadow-lift">
                    <div className="flex flex-col items-center bg-success-light px-6 py-9 text-center sm:px-10">
                        <span className="flex h-16 w-16 items-center justify-center rounded-2xl bg-success text-white">
                            <CheckCircle2 className="h-8 w-8" />
                        </span>
                        <h1 className="mt-5 font-display text-3xl font-semibold text-chocolate-700">
                            Payment received! 💗
                        </h1>
                        <p className="mt-2 text-sm text-chocolate-500">
                            Thank you, {resellerFirstName}. We have recorded your {payment.kind_label.toLowerCase()} for
                            order <span className="font-mono font-semibold">#{order.number}</span>.
                        </p>
                    </div>

                    <div className="p-6 sm:p-10">
                        <dl className="grid gap-3 sm:grid-cols-3">
                            <div className="rounded-2xl border border-cream-300 bg-cream-50 p-4 text-center">
                                <dt className="text-[11px] font-semibold uppercase tracking-[0.14em] text-chocolate-400">
                                    Amount paid
                                </dt>
                                <dd className="mt-1 font-display text-2xl font-semibold text-success">
                                    <Money value={payment.amount} />
                                </dd>
                            </div>
                            <div className="rounded-2xl border border-cream-300 bg-cream-50 p-4 text-center">
                                <dt className="text-[11px] font-semibold uppercase tracking-[0.14em] text-chocolate-400">
                                    Order total
                                </dt>
                                <dd className="mt-1 font-display text-2xl font-semibold text-chocolate-700">
                                    <Money value={order.total} />
                                </dd>
                            </div>
                            <div
                                className={clsx(
                                    'rounded-2xl border p-4 text-center',
                                    settled
                                        ? 'border-success/25 bg-success-light'
                                        : 'border-caramel/30 bg-caramel-soft/15',
                                )}
                            >
                                <dt className="text-[11px] font-semibold uppercase tracking-[0.14em] text-chocolate-400">
                                    Remaining balance
                                </dt>
                                <dd
                                    className={clsx(
                                        'mt-1 font-display text-2xl font-semibold',
                                        settled ? 'text-success' : 'text-caramel-dark',
                                    )}
                                >
                                    <Money value={order.balance} />
                                </dd>
                            </div>
                        </dl>

                        <div className="mt-6 rounded-2xl border border-cream-300 bg-cream-50 p-5">
                            <p className="text-sm leading-relaxed text-chocolate-600">
                                {settled ? (
                                    <>
                                        Your order is <strong className="font-semibold text-success">fully paid</strong>{' '}
                                        and confirmed. Our team is on it — we will message you the moment it is ready.
                                    </>
                                ) : (
                                    <>
                                        Your order is now{' '}
                                        <strong className="font-semibold text-chocolate-700">confirmed</strong> and will
                                        be prepared by our team. A balance of{' '}
                                        <strong className="font-semibold text-caramel-dark">
                                            <Money value={order.balance} />
                                        </strong>{' '}
                                        is still due before pickup.
                                    </>
                                )}
                            </p>

                            <dl className="mt-4 grid gap-x-6 gap-y-2 border-t border-cream-300 pt-4 text-sm sm:grid-cols-2">
                                {[
                                    ['Receipt number', payment.receipt_number, 'font-mono'],
                                    ['Payment method', payment.method_label],
                                    ['Payment date', `${payment.paid_on} · ${payment.paid_time}`],
                                    ['Reference', payment.reference, 'font-mono'],
                                ].map(([label, value, cls]) => (
                                    <div key={label} className="flex justify-between gap-3">
                                        <dt className="text-chocolate-400">{label}</dt>
                                        <dd className={clsx('font-medium text-chocolate-700', cls)}>{value}</dd>
                                    </div>
                                ))}
                            </dl>
                        </div>

                        <div className="mt-6 grid gap-2 sm:grid-cols-3">
                            <ButtonLink href={route('portal.orders.show', order.number)} className="py-3">
                                <Receipt className="h-4 w-4" />
                                View order
                            </ButtonLink>
                            <ButtonLink
                                href={route('receipts.show', payment.receipt_number)}
                                variant="ghost"
                                className="py-3"
                            >
                                <FileText className="h-4 w-4" />
                                View receipt
                            </ButtonLink>
                            <ButtonLink href={route('portal.messages.index')} variant="ghost" className="py-3">
                                <MessageCircle className="h-4 w-4" />
                                Message us
                            </ButtonLink>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
