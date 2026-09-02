import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { ArrowLeft, Loader2, Lock, ShieldCheck, Smartphone, Wand2 } from 'lucide-react';
import { Button, FlashToasts, Logo, Money } from '@/Components/Lileu/ui';

const WALLETS = ['GCash', 'Maya', 'BPI', 'BDO', 'UnionBank', 'Landbank'];

export default function QrPh({ payment, order, qrSvg, checkoutUrl, demoMode }) {
    const { brand } = usePage().props;
    const [confirming, setConfirming] = useState(false);

    // A webhook can confirm this payment while the reseller is still looking at
    // the QR, so poll quietly and move them along the moment it lands.
    useEffect(() => {
        if (demoMode) return undefined;

        const timer = setInterval(async () => {
            try {
                const response = await fetch(route('pay.status', payment.receipt_number), {
                    headers: { Accept: 'application/json' },
                });
                const body = await response.json();

                if (body.redirect) {
                    clearInterval(timer);
                    router.visit(body.redirect);
                }
            } catch {
                /* transient network hiccup — the next tick retries */
            }
        }, 4000);

        return () => clearInterval(timer);
    }, [demoMode, payment.receipt_number]);

    const heading =
        payment.kind === 'downpayment'
            ? `Complete your ${order.downpayment_percent}% downpayment`
            : payment.kind === 'balance'
              ? 'Settle your remaining balance'
              : 'Complete your payment';

    return (
        <div className="min-h-screen bg-cream-fade">
            <Head title="Pay with QR Ph" />
            <FlashToasts />

            <div className="mx-auto max-w-3xl px-4 py-10 sm:py-16">
                <div className="mb-8 flex items-center justify-between">
                    <span className="flex items-center gap-2.5">
                        <Logo className="h-10 w-10" />
                        <span className="leading-tight">
                            <span className="block font-display text-lg font-semibold text-chocolate-700">
                                {brand?.name}
                            </span>
                            <span className="block text-[11px] font-medium uppercase tracking-[0.16em] text-blush-500">
                                Secure payment
                            </span>
                        </span>
                    </span>

                    <button
                        type="button"
                        onClick={() => router.visit(route('portal.orders.show', order.number))}
                        className="inline-flex items-center gap-1.5 text-sm font-medium text-chocolate-400 transition hover:text-chocolate-700"
                    >
                        <ArrowLeft className="h-4 w-4" /> Back to order
                    </button>
                </div>

                <div className="overflow-hidden rounded-3xl border border-cream-300 bg-vanilla shadow-lift">
                    <div className="bg-choco-fade px-6 py-7 text-center sm:px-10">
                        <h1 className="font-display text-2xl font-semibold text-cream-100 sm:text-3xl">{heading}</h1>
                        <p className="mt-1.5 font-mono text-sm text-blush-300">Order #{order.number}</p>
                    </div>

                    <div className="grid gap-8 p-6 sm:p-10 lg:grid-cols-[1fr_1.1fr]">
                        {/* Amounts */}
                        <div>
                            <dl className="space-y-3">
                                <div className="flex items-center justify-between border-b border-cream-200 pb-3">
                                    <dt className="text-xs font-semibold uppercase tracking-[0.14em] text-chocolate-400">
                                        Order total
                                    </dt>
                                    <dd className="font-display text-xl font-semibold text-chocolate-700">
                                        <Money value={order.total} />
                                    </dd>
                                </div>

                                <div className="rounded-2xl bg-blush-50 px-4 py-4 text-center">
                                    <dt className="text-xs font-semibold uppercase tracking-[0.14em] text-blush-500">
                                        Pay now
                                    </dt>
                                    <dd className="mt-1 font-display text-4xl font-semibold text-chocolate-700">
                                        <Money value={payment.amount} />
                                    </dd>
                                    <p className="mt-1 text-xs text-chocolate-400">{payment.kind_label}</p>
                                </div>

                                <div className="flex items-center justify-between pt-1">
                                    <dt className="text-xs font-semibold uppercase tracking-[0.14em] text-chocolate-400">
                                        Balance after payment
                                    </dt>
                                    <dd className="font-display text-lg font-semibold text-caramel-dark">
                                        <Money value={order.balance_after} />
                                    </dd>
                                </div>
                            </dl>

                            <div className="mt-6 space-y-2.5 rounded-2xl border border-cream-300 bg-cream-50 p-4">
                                <p className="flex items-start gap-2 text-xs leading-relaxed text-chocolate-500">
                                    <ShieldCheck className="mt-0.5 h-4 w-4 shrink-0 text-success" />
                                    Processed by PayMongo over QR Ph. {brand?.name} never sees your bank or wallet
                                    credentials.
                                </p>
                                <p className="flex items-start gap-2 text-xs leading-relaxed text-chocolate-500">
                                    <Lock className="mt-0.5 h-4 w-4 shrink-0 text-chocolate-400" />
                                    Receipt <span className="font-mono">{payment.receipt_number}</span> is issued the
                                    moment payment clears.
                                </p>
                            </div>
                        </div>

                        {/* QR */}
                        <div className="flex flex-col items-center">
                            <div
                                className="rounded-3xl border-4 border-chocolate-700 bg-vanilla p-4 shadow-soft [&>svg]:h-auto [&>svg]:w-full"
                                dangerouslySetInnerHTML={{ __html: qrSvg }}
                            />

                            <p className="mt-5 text-center text-sm font-medium text-chocolate-600">
                                <Smartphone className="mr-1.5 inline h-4 w-4 text-blush-500" />
                                Scan using your supported bank or e-wallet
                            </p>

                            <div className="mt-3 flex flex-wrap justify-center gap-1.5">
                                {WALLETS.map((wallet) => (
                                    <span
                                        key={wallet}
                                        className="rounded-lg bg-cream-200 px-2.5 py-1 text-[11px] font-medium text-chocolate-500"
                                    >
                                        {wallet}
                                    </span>
                                ))}
                            </div>

                            {checkoutUrl && (
                                <a
                                    href={checkoutUrl}
                                    className="btn-ghost mt-5 w-full"
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    Open in PayMongo instead
                                </a>
                            )}

                            {demoMode ? (
                                <div className="mt-6 w-full rounded-2xl border border-caramel/30 bg-caramel-soft/15 p-4">
                                    <p className="text-xs leading-relaxed text-chocolate-500">
                                        <strong className="font-semibold text-caramel-dark">Demo mode.</strong> No
                                        PayMongo keys are configured, so this QR is simulated. Confirm below to walk
                                        through the rest of the flow.
                                    </p>
                                    <Button
                                        onClick={() => {
                                            setConfirming(true);
                                            router.post(route('pay.simulate', payment.receipt_number), {}, {
                                                onFinish: () => setConfirming(false),
                                            });
                                        }}
                                        disabled={confirming}
                                        className="mt-3 w-full"
                                    >
                                        {confirming ? (
                                            <>
                                                <Loader2 className="h-4 w-4 animate-spin" /> Confirming…
                                            </>
                                        ) : (
                                            <>
                                                <Wand2 className="h-4 w-4" /> Simulate successful payment
                                            </>
                                        )}
                                    </Button>
                                </div>
                            ) : (
                                <p className="mt-6 flex items-center justify-center gap-2 text-xs text-chocolate-400">
                                    <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                    Waiting for your payment to clear…
                                </p>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
