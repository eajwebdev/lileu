import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import clsx from 'clsx';
import { ArrowLeft, Printer, Receipt, Search } from 'lucide-react';
import {
    Badge,
    Card,
    EmptyState,
    FlashToasts,
    Input,
    Logo,
    Money,
    Pagination,
} from '@/Components/Lileu/ui';

const METHOD_LABELS = { cash: 'Cash', gcash: 'GCash', qrph: 'QR Ph', card: 'Card' };

export default function History({ sales, filters, todayTotal }) {
    const { brand } = usePage().props;
    const [term, setTerm] = useState(filters.q ?? '');

    return (
        <div className="min-h-screen bg-cream-100">
            <Head title="Sales history" />
            <FlashToasts />

            <header className="flex items-center justify-between gap-4 bg-chocolate-700 px-4 py-3 text-cream-100">
                <div className="flex items-center gap-2.5">
                    <Logo className="h-9 w-9" />
                    <span className="leading-tight">
                        <span className="block font-display text-base font-semibold">{brand?.name}</span>
                        <span className="block text-[11px] uppercase tracking-[0.16em] text-blush-300">
                            Sales history
                        </span>
                    </span>
                </div>

                <button
                    type="button"
                    onClick={() => router.visit(route('pos.index'))}
                    className="inline-flex items-center gap-1.5 rounded-xl border border-cream-200/25 px-3.5 py-2 text-sm font-semibold text-cream-100 transition hover:bg-cream-100/10"
                >
                    <ArrowLeft className="h-4 w-4" /> Back to terminal
                </button>
            </header>

            <div className="mx-auto max-w-5xl px-4 py-6 sm:px-6">
                <Card className="card-pad mb-5 flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p className="text-sm text-chocolate-400">Collected today</p>
                        <p className="font-display text-2xl font-semibold text-chocolate-700">
                            <Money value={todayTotal} />
                        </p>
                    </div>

                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            router.get(route('pos.sales.index'), { q: term || undefined }, { preserveState: true });
                        }}
                        className="relative min-w-52"
                    >
                        <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-chocolate-300" />
                        <Input
                            value={term}
                            onChange={(e) => setTerm(e.target.value)}
                            placeholder="Sale number…"
                            className="pl-10"
                        />
                    </form>
                </Card>

                <Card className="card-pad">
                    {sales.data.length === 0 ? (
                        <EmptyState
                            icon={Receipt}
                            title="No sales yet"
                            description="Completed counter sales show up here with a reprintable slip."
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="table-lileu min-w-full">
                                <thead>
                                    <tr>
                                        <th>Sale</th>
                                        <th>Cashier</th>
                                        <th>Customer</th>
                                        <th className="text-center">Items</th>
                                        <th>Method</th>
                                        <th className="text-right">Total</th>
                                        <th className="text-right">Slip</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {sales.data.map((sale) => (
                                        <tr key={sale.sale_number} className="transition hover:bg-cream-50">
                                            <td>
                                                <span className="font-mono text-xs font-semibold text-chocolate-700">
                                                    {sale.sale_number}
                                                </span>
                                                <p className="text-[11px] text-chocolate-300">{sale.sold_at}</p>
                                            </td>
                                            <td className="text-xs">{sale.cashier ?? '—'}</td>
                                            <td className="text-xs">{sale.customer_name ?? '—'}</td>
                                            <td className="text-center tabular-nums">{sale.items_count}</td>
                                            <td>
                                                <Badge tone="chocolate">
                                                    {METHOD_LABELS[sale.method] ?? sale.method}
                                                </Badge>
                                            </td>
                                            <td
                                                className={clsx(
                                                    'text-right font-semibold tabular-nums',
                                                    sale.status === 'void'
                                                        ? 'text-chocolate-300 line-through'
                                                        : 'text-chocolate-700',
                                                )}
                                            >
                                                <Money value={sale.total} />
                                            </td>
                                            <td className="text-right">
                                                <a
                                                    href={route('pos.sales.receipt', sale.sale_number)}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="inline-flex rounded-lg p-1.5 text-chocolate-400 transition hover:bg-cream-200 hover:text-chocolate-700"
                                                    aria-label="Reprint slip"
                                                >
                                                    <Printer className="h-4 w-4" />
                                                </a>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    <Pagination links={sales.links} className="mt-6" />
                </Card>
            </div>
        </div>
    );
}
