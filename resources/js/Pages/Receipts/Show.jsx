import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Download, FileText, Printer, Wallet } from 'lucide-react';
import Receipt from '@/Components/Lileu/Receipt';
import { Button, ButtonLink, FlashToasts, Money } from '@/Components/Lileu/ui';

export default function Show({ data, canPayBalance }) {
    const { auth } = usePage().props;
    const isAdmin = auth?.user?.role === 'admin' || auth?.user?.role === 'cashier';
    const backHref = isAdmin
        ? route('admin.orders.show', data.order.number)
        : route('portal.orders.show', data.order.number);

    return (
        <div className="min-h-screen bg-cream-fade py-8 print:bg-white print:py-0">
            <Head title={`Receipt ${data.receipt.number}`} />
            <FlashToasts />

            <div className="mx-auto max-w-3xl px-4 print:max-w-none print:px-0">
                {/* Actions — hidden when printing */}
                <div className="no-print mb-6 flex flex-wrap items-center justify-between gap-3">
                    <Link
                        href={backHref}
                        className="inline-flex items-center gap-1.5 text-sm font-medium text-chocolate-400 transition hover:text-chocolate-700"
                    >
                        <ArrowLeft className="h-4 w-4" /> Back to order
                    </Link>

                    <div className="flex flex-wrap gap-2">
                        <a
                            href={route('receipts.print', data.receipt.number)}
                            target="_blank"
                            rel="noreferrer"
                            className="btn-ghost"
                        >
                            <Printer className="h-4 w-4" /> Print
                        </a>
                        <a href={route('receipts.pdf', data.receipt.number)} className="btn-ghost">
                            <Download className="h-4 w-4" /> Download PDF
                        </a>
                        <ButtonLink
                            href={route('receipts.summary', data.order.number)}
                            variant="secondary"
                        >
                            <FileText className="h-4 w-4" /> Payment summary
                        </ButtonLink>
                    </div>
                </div>

                <Receipt data={data} />

                {/* Balance call to action */}
                {canPayBalance && data.totals.balance > 0 && (
                    <div className="no-print mt-5 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-caramel/30 bg-caramel-soft/15 px-5 py-4">
                        <div>
                            <p className="text-sm font-semibold text-chocolate-700">
                                Remaining balance on this order
                            </p>
                            <p className="font-display text-2xl font-semibold text-caramel-dark">
                                <Money value={data.totals.balance} />
                            </p>
                        </div>
                        <Button onClick={() => router.post(route('portal.orders.pay', data.order.number))}>
                            <Wallet className="h-4 w-4" /> Pay remaining balance
                        </Button>
                    </div>
                )}
            </div>
        </div>
    );
}
