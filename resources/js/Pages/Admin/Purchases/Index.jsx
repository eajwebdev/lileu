import { router } from '@inertiajs/react';
import { useState } from 'react';
import { ChevronDown, ChevronRight, Plus, Search, ShoppingBasket, Trash2 } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Button, ButtonLink, Card, EmptyState, Input, Money, Pagination, peso } from '@/Components/Lileu/ui';

export default function Index({ purchases, month, filters, totals }) {
    const [term, setTerm] = useState(filters.q ?? '');
    const [open, setOpen] = useState({});

    const reload = (params) =>
        router.get(route('admin.purchases.index'), { month, q: term, ...params }, {
            preserveState: true,
            replace: true,
        });

    return (
        <AdminLayout
            title="Purchases"
            subtitle="Every ingredient run, line by line."
            action={
                <div className="flex items-center gap-2">
                    <Input
                        type="month"
                        value={month}
                        onChange={(e) => reload({ month: e.target.value })}
                        className="w-auto"
                    />
                    <ButtonLink href={route('admin.purchases.create')}>
                        <Plus className="h-4 w-4" /> New purchase
                    </ButtonLink>
                </div>
            }
        >
            <div className="space-y-4">
                <Card className="card-pad">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <div className="flex items-center gap-3">
                            <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-caramel-soft/30 text-caramel-dark">
                                <ShoppingBasket className="h-5 w-5" />
                            </span>
                            <div>
                                <p className="text-sm text-chocolate-400">Spent on ingredients this month</p>
                                <p className="font-display text-2xl font-semibold text-chocolate-700">
                                    <Money value={totals.amount} />
                                    <span className="ml-2 text-sm font-normal text-chocolate-400">
                                        across {totals.count} {totals.count === 1 ? 'purchase' : 'purchases'}
                                    </span>
                                </p>
                            </div>
                        </div>

                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                reload({});
                            }}
                            className="relative min-w-0 flex-1 sm:max-w-xs"
                        >
                            <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-chocolate-300" />
                            <Input
                                value={term}
                                onChange={(e) => setTerm(e.target.value)}
                                placeholder="Search ingredient or supplier…"
                                className="pl-10"
                            />
                        </form>
                    </div>
                </Card>

                {purchases.data.length === 0 ? (
                    <EmptyState
                        icon={ShoppingBasket}
                        title="No purchases this month"
                        description="Record a market run and every ingredient's price stays current."
                        action={
                            <ButtonLink href={route('admin.purchases.create')}>
                                <Plus className="h-4 w-4" /> New purchase
                            </ButtonLink>
                        }
                    />
                ) : (
                    <div className="space-y-2.5">
                        {purchases.data.map((purchase) => {
                            const expanded = !!open[purchase.id];

                            return (
                                <Card key={purchase.id} className="overflow-hidden">
                                    <button
                                        type="button"
                                        onClick={() => setOpen((prev) => ({ ...prev, [purchase.id]: !expanded }))}
                                        className="flex w-full items-center gap-3 px-5 py-4 text-left transition hover:bg-cream-100/60"
                                    >
                                        {expanded ? (
                                            <ChevronDown className="h-4 w-4 shrink-0 text-chocolate-400" />
                                        ) : (
                                            <ChevronRight className="h-4 w-4 shrink-0 text-chocolate-400" />
                                        )}

                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate font-medium text-chocolate-700">
                                                {purchase.description}
                                            </span>
                                            <span className="block text-[11px] text-chocolate-400">
                                                {[
                                                    purchase.purchased_on,
                                                    purchase.supplier,
                                                    purchase.reference,
                                                    `${purchase.items.length} ${purchase.items.length === 1 ? 'item' : 'items'}`,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                            </span>
                                        </span>

                                        <span className="shrink-0 font-display text-lg font-semibold tabular-nums text-chocolate-700">
                                            <Money value={purchase.amount} />
                                        </span>
                                    </button>

                                    {expanded && (
                                        <div className="border-t border-cream-200 bg-cream-50/60 px-5 py-4">
                                            <div className="overflow-x-auto">
                                                <table className="table-lileu min-w-full">
                                                    <thead>
                                                        <tr>
                                                            <th>Ingredient</th>
                                                            <th className="text-right">Qty</th>
                                                            <th className="text-right">Price / unit</th>
                                                            <th className="text-right">Total</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {purchase.items.map((item) => (
                                                            <tr key={item.id}>
                                                                <td className="font-medium text-chocolate-700">
                                                                    {item.name}
                                                                </td>
                                                                <td className="text-right tabular-nums">
                                                                    {item.quantity} {item.unit}
                                                                </td>
                                                                <td className="text-right tabular-nums">
                                                                    {peso(item.unit_price)}
                                                                </td>
                                                                <td className="text-right font-semibold tabular-nums text-chocolate-700">
                                                                    <Money value={item.line_total} />
                                                                </td>
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            </div>

                                            {purchase.notes && (
                                                <p className="mt-3 text-sm text-chocolate-500">{purchase.notes}</p>
                                            )}

                                            <div className="mt-3 flex items-center justify-between gap-4 border-t border-cream-200 pt-3">
                                                <p className="text-[11px] text-chocolate-400">
                                                    {purchase.recorded_by
                                                        ? `Recorded by ${purchase.recorded_by}`
                                                        : 'Recorded'}
                                                </p>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    onClick={() => {
                                                        if (!confirm('Remove this purchase from the ledger?')) return;
                                                        router.delete(route('admin.purchases.destroy', purchase.id), {
                                                            preserveScroll: true,
                                                        });
                                                    }}
                                                    className="text-cherry"
                                                >
                                                    <Trash2 className="h-4 w-4" /> Remove
                                                </Button>
                                            </div>
                                        </div>
                                    )}
                                </Card>
                            );
                        })}
                    </div>
                )}

                <Pagination links={purchases.links} />
            </div>
        </AdminLayout>
    );
}
