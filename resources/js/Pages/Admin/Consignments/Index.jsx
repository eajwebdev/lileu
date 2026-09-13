import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import clsx from 'clsx';
import { HandCoins, PackageOpen, Plus, Search, TriangleAlert } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Badge, ButtonLink, Card, EmptyState, Input, Money, Pagination, Select } from '@/Components/Lileu/ui';

const STATUS = {
    open: ['Out on consignment', 'amber'],
    settled: ['Settled', 'green'],
    cancelled: ['Cancelled', 'muted-red'],
};

export default function Index({ consignments, filters, summary }) {
    const [term, setTerm] = useState(filters.q ?? '');

    const apply = (next) =>
        router.get(route('admin.consignments.index'), { ...filters, ...next }, { preserveState: true, replace: true });

    return (
        <AdminLayout
            title="Consignments"
            subtitle="Stock handed out to sell, and what has come back."
            action={
                <ButtonLink href={route('admin.consignments.create')}>
                    <Plus className="h-4 w-4" /> Issue a batch
                </ButtonLink>
            }
        >
            <div className="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Card className="card-pad flex items-center gap-4">
                    <PackageOpen className="h-8 w-8 shrink-0 text-caramel" />
                    <div>
                        <p className="text-sm text-chocolate-400">Open batches</p>
                        <p className="font-display text-2xl font-semibold text-chocolate-700">
                            {summary.open_batches}
                        </p>
                    </div>
                </Card>
                <Card className="card-pad">
                    <p className="text-sm text-chocolate-400">Value out in the field</p>
                    <p className="mt-1 font-display text-2xl font-semibold text-chocolate-700">
                        <Money value={summary.value_out} decimals={0} />
                    </p>
                </Card>
                <Card className="card-pad">
                    <p className="text-sm text-chocolate-400">Cash still to collect</p>
                    <p className="mt-1 font-display text-2xl font-semibold text-caramel-dark">
                        <Money value={summary.due} decimals={0} />
                    </p>
                </Card>
                <Card className="card-pad">
                    <p className="flex items-center gap-1.5 text-sm text-chocolate-400">
                        <TriangleAlert className="h-4 w-4 text-cherry" /> Losses at cost
                    </p>
                    <p className="mt-1 font-display text-2xl font-semibold text-cherry-dark">
                        <Money value={summary.losses} decimals={0} />
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
                            placeholder="Batch number or seller…"
                            className="pl-10"
                        />
                    </form>

                    <Select
                        value={filters.status ?? ''}
                        onChange={(e) => apply({ status: e.target.value || undefined })}
                        className="w-auto min-w-44"
                    >
                        <option value="">All batches</option>
                        <option value="open">Still out</option>
                        <option value="settled">Settled</option>
                        <option value="cancelled">Cancelled</option>
                    </Select>
                </div>

                {consignments.data.length === 0 ? (
                    <EmptyState
                        icon={HandCoins}
                        title="No consignments yet"
                        description="Hand a batch to a student or vendor, then collect the takings and whatever did not sell."
                        action={
                            <ButtonLink href={route('admin.consignments.create')}>Issue a batch</ButtonLink>
                        }
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="table-lileu min-w-full">
                            <thead>
                                <tr>
                                    <th>Batch</th>
                                    <th>Seller</th>
                                    <th>Issued</th>
                                    <th className="text-center">Out / Sold</th>
                                    <th>Status</th>
                                    <th className="text-right">Sold value</th>
                                    <th className="text-right">Collected</th>
                                    <th className="text-right">Due</th>
                                </tr>
                            </thead>
                            <tbody>
                                {consignments.data.map((row) => {
                                    const [label, tone] = STATUS[row.status] ?? [row.status, 'blush'];

                                    return (
                                        <tr key={row.number} className="transition hover:bg-cream-50">
                                            <td>
                                                <Link
                                                    href={route('admin.consignments.show', row.number)}
                                                    className="font-mono text-xs font-semibold text-chocolate-700 hover:underline"
                                                >
                                                    {row.number}
                                                </Link>
                                                {row.due_on && (
                                                    <p className="text-[11px] text-chocolate-300">
                                                        collect by {row.due_on}
                                                    </p>
                                                )}
                                            </td>
                                            <td>
                                                <p className="max-w-40 truncate font-medium text-chocolate-700">
                                                    {row.seller}
                                                </p>
                                                {row.seller_person && (
                                                    <p className="max-w-40 truncate text-[11px] text-chocolate-400">
                                                        {row.seller_person}
                                                    </p>
                                                )}
                                                <p className="font-mono text-[11px] text-chocolate-300">
                                                    {row.seller_code}
                                                </p>
                                            </td>
                                            <td className="whitespace-nowrap text-xs">{row.issued_on}</td>
                                            <td className="text-center text-xs tabular-nums">
                                                <span className="font-semibold text-chocolate-700">
                                                    {row.quantity_issued}
                                                </span>
                                                <span className="text-chocolate-300"> / </span>
                                                <span className="font-semibold text-success">{row.quantity_sold}</span>
                                                {row.outstanding > 0 && (
                                                    <p className="text-[11px] text-caramel-dark">
                                                        {row.outstanding} still out
                                                    </p>
                                                )}
                                            </td>
                                            <td>
                                                <Badge tone={tone}>{label}</Badge>
                                            </td>
                                            <td className="text-right tabular-nums text-chocolate-700">
                                                <Money value={row.sold_value} />
                                            </td>
                                            <td className="text-right tabular-nums text-success">
                                                <Money value={row.amount_collected} />
                                            </td>
                                            <td
                                                className={clsx(
                                                    'text-right font-semibold tabular-nums',
                                                    row.amount_due > 0 ? 'text-caramel-dark' : 'text-success',
                                                )}
                                            >
                                                <Money value={row.amount_due} />
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination links={consignments.links} className="mt-6" />
            </Card>
        </AdminLayout>
    );
}
