import { Link, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import clsx from 'clsx';
import {
    ArrowLeft,
    Ban,
    Download,
    HandCoins,
    PackageCheck,
    Phone,
    Printer,
    TriangleAlert,
} from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Badge, Button, ButtonLink, Card, Field, Input, Money, Select, Textarea } from '@/Components/Lileu/ui';

const STATUS = {
    open: ['Out on consignment', 'amber'],
    settled: ['Settled', 'green'],
    cancelled: ['Cancelled', 'muted-red'],
};

const BUCKETS = [
    ['sold', 'Sold', 'text-success'],
    ['returned', 'Returned', 'text-chocolate-600'],
    ['expired', 'Expired', 'text-caramel-dark'],
    ['damaged', 'Damaged', 'text-caramel-dark'],
    ['missing', 'Missing / other', 'text-cherry'],
];

export default function Show({ consignment, seller }) {
    const [label, tone] = STATUS[consignment.status] ?? [consignment.status, 'blush'];
    const open = consignment.status === 'open';

    // Per-item buckets. Every unit that comes back has to land in exactly one.
    const [rows, setRows] = useState(() =>
        Object.fromEntries(
            consignment.items.map((item) => [
                item.id,
                { sold: '', returned: '', expired: '', damaged: '', missing: '' },
            ]),
        ),
    );

    const form = useForm({
        lines: [],
        settled_on: new Date().toISOString().slice(0, 10),
        amount_collected: '',
        method: 'cash',
        reference: '',
        is_final: false,
        notes: '',
    });

    const set = (itemId, bucket, value) =>
        setRows((prev) => ({
            ...prev,
            [itemId]: { ...prev[itemId], [bucket]: value === '' ? '' : Math.max(0, Number(value)) },
        }));

    const n = (v) => (v === '' ? 0 : Number(v));

    const computed = useMemo(() => {
        let soldValue = 0;
        let over = [];

        const lines = consignment.items.map((item) => {
            const r = rows[item.id] ?? {};
            const accounted = n(r.sold) + n(r.returned) + n(r.expired) + n(r.damaged) + n(r.missing);

            if (accounted > item.outstanding) {
                over.push(item.name);
            }

            soldValue += n(r.sold) * item.unit_price;

            return {
                consignment_item_id: item.id,
                sold: n(r.sold),
                returned: n(r.returned),
                expired: n(r.expired),
                damaged: n(r.damaged),
                missing: n(r.missing),
                accounted,
            };
        });

        const remaining = lines.reduce(
            (total, line, index) => total + Math.max(0, consignment.items[index].outstanding - line.accounted),
            0,
        );

        return { lines, soldValue, over, remaining, anything: lines.some((l) => l.accounted > 0) };
    }, [rows, consignment.items]);

    const returnEverythingRemaining = () =>
        setRows((prev) =>
            Object.fromEntries(
                consignment.items.map((item) => {
                    const row = prev[item.id] ?? {};
                    const otherwiseAccounted =
                        n(row.sold) + n(row.expired) + n(row.damaged) + n(row.missing);

                    return [
                        item.id,
                        {
                            ...row,
                            returned: Math.max(0, item.outstanding - otherwiseAccounted),
                        },
                    ];
                }),
            ),
        );

    const submit = (e) => {
        e.preventDefault();

        form.transform((data) => ({
            ...data,
            lines: computed.lines.filter((l) => l.accounted > 0),
        }));

        form.post(route('admin.consignments.settle', consignment.number), {
            preserveScroll: true,
            onSuccess: () =>
                setRows(
                    Object.fromEntries(
                        consignment.items.map((item) => [
                            item.id,
                            { sold: '', returned: '', expired: '', damaged: '', missing: '' },
                        ]),
                    ),
                ),
        });
    };

    return (
        <AdminLayout
            title={consignment.number}
            subtitle={`Issued ${consignment.issued_on} to ${seller.business_name || seller.name}`}
            action={
                <div className="flex flex-wrap gap-2">
                    <a
                        href={route('admin.consignments.slip', consignment.number)}
                        target="_blank"
                        rel="noreferrer"
                        className="btn-ghost"
                    >
                        <Printer className="h-4 w-4" /> Issue slip
                    </a>
                    <a
                        href={route('admin.consignments.slip', { consignment: consignment.number, pdf: 1 })}
                        className="btn-ghost"
                    >
                        <Download className="h-4 w-4" /> PDF
                    </a>
                    <Link
                        href={route('admin.consignments.index')}
                        className="inline-flex items-center gap-1.5 px-2 text-sm font-medium text-chocolate-400 transition hover:text-chocolate-700"
                    >
                        <ArrowLeft className="h-4 w-4" /> All
                    </Link>
                </div>
            }
        >
            <div className="grid gap-5 xl:grid-cols-[1.7fr_1fr] xl:items-start">
                <div className="space-y-5">
                    {/* What went out */}
                    <Card className="card-pad">
                        <div className="mb-4 flex flex-wrap items-center gap-2">
                            <Badge tone={tone}>{label}</Badge>
                            {consignment.totals.outstanding > 0 && (
                                <Badge tone="caramel">{consignment.totals.outstanding} pcs still out</Badge>
                            )}
                            {consignment.due_on && (
                                <span className="text-xs text-chocolate-400">Collect by {consignment.due_on}</span>
                            )}
                        </div>

                        <div className="overflow-x-auto">
                            <table className="table-lileu min-w-full">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th className="text-right">Unit price</th>
                                        <th className="text-center">Out</th>
                                        <th className="text-center">Sold</th>
                                        <th className="text-center">Back</th>
                                        <th className="text-center">Lost</th>
                                        <th className="text-center">Still out</th>
                                        <th className="text-right">Sold value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {consignment.items.map((item) => (
                                        <tr key={item.id}>
                                            <td>
                                                <p className="font-medium text-chocolate-700">
                                                    {item.name}
                                                    {!item.tracks_stock && (
                                                        <span className="ml-1.5 text-[10px] font-semibold uppercase tracking-wide text-blush-dark">
                                                            Made to order
                                                        </span>
                                                    )}
                                                </p>
                                                <p className="font-mono text-[11px] text-chocolate-300">{item.sku}</p>
                                            </td>
                                            <td className="text-right tabular-nums">
                                                <Money value={item.unit_price} />
                                                <p className="text-[11px] text-chocolate-300">
                                                    keeps <Money value={item.margin} />
                                                </p>
                                            </td>
                                            <td className="text-center font-semibold tabular-nums">
                                                {item.quantity_issued}
                                            </td>
                                            <td className="text-center tabular-nums text-success">
                                                {item.quantity_sold}
                                            </td>
                                            <td className="text-center tabular-nums">{item.quantity_returned}</td>
                                            <td className="text-center tabular-nums text-cherry-dark">
                                                {item.quantity_expired + item.quantity_damaged + item.quantity_missing}
                                            </td>
                                            <td
                                                className={clsx(
                                                    'text-center font-semibold tabular-nums',
                                                    item.outstanding > 0 ? 'text-caramel-dark' : 'text-chocolate-300',
                                                )}
                                            >
                                                {item.outstanding}
                                            </td>
                                            <td className="text-right font-semibold tabular-nums text-chocolate-700">
                                                <Money value={item.sold_value} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {consignment.notes && (
                            <p className="mt-4 rounded-xl border border-cream-300 bg-cream-50 px-4 py-3 text-sm text-chocolate-600">
                                {consignment.notes}
                            </p>
                        )}
                    </Card>

                    {/* Settlement form */}
                    {open && (
                        <Card className="card-pad">
                            <h2 className="font-display text-lg font-semibold text-chocolate-700">
                                Record a collection
                            </h2>
                            <p className="text-sm text-chocolate-400">
                                Count what sold and what came back. Good returns go straight back on the shelf;
                                expired, damaged, missing, and other unusable units are written off. Add the exact
                                situation in the notes.
                            </p>

                            <div className="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-cream-300 bg-cream-50 px-4 py-3">
                                <p className="text-xs text-chocolate-400">
                                    For end-of-day pickup, enter sold or lost units first, then return everything else.
                                </p>
                                <Button type="button" variant="ghost" onClick={returnEverythingRemaining}>
                                    Mark all remaining returned
                                </Button>
                            </div>

                            <form onSubmit={submit} className="mt-5">
                                <div className="overflow-x-auto">
                                    <table className="table-lileu min-w-full">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th className="text-center">Still out</th>
                                                {BUCKETS.map(([key, label]) => (
                                                    <th key={key} className="w-20 text-center">
                                                        {label}
                                                    </th>
                                                ))}
                                                <th className="text-center">Left</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {consignment.items.map((item) => {
                                                const r = rows[item.id] ?? {};
                                                const accounted =
                                                    n(r.sold) + n(r.returned) + n(r.expired) + n(r.damaged) + n(r.missing);
                                                const remaining = item.outstanding - accounted;

                                                return (
                                                    <tr
                                                        key={item.id}
                                                        className={clsx(remaining < 0 && 'bg-cherry/5')}
                                                    >
                                                        <td className="font-medium text-chocolate-700">
                                                            {item.name}
                                                            {!item.tracks_stock && (
                                                                <span className="block text-[10px] font-semibold uppercase tracking-wide text-blush-dark">
                                                                    Made to order
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td className="text-center font-semibold tabular-nums text-chocolate-500">
                                                            {item.outstanding}
                                                        </td>
                                                        {BUCKETS.map(([key]) => (
                                                            <td key={key}>
                                                                <input
                                                                    type="number"
                                                                    min={0}
                                                                    max={item.outstanding}
                                                                    value={r[key] ?? ''}
                                                                    disabled={item.outstanding === 0}
                                                                    onChange={(e) => set(item.id, key, e.target.value)}
                                                                    placeholder="0"
                                                                    className="h-9 w-full rounded-lg border-cream-300 bg-vanilla text-center text-sm tabular-nums focus:border-blush-400 focus:ring-blush-200 disabled:bg-cream-100"
                                                                />
                                                            </td>
                                                        ))}
                                                        <td
                                                            className={clsx(
                                                                'text-center text-sm font-semibold tabular-nums',
                                                                remaining < 0
                                                                    ? 'text-cherry'
                                                                    : remaining === 0
                                                                      ? 'text-success'
                                                                      : 'text-chocolate-300',
                                                            )}
                                                        >
                                                            {remaining}
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>

                                {computed.over.length > 0 && (
                                    <p className="mt-3 flex items-start gap-2 rounded-xl bg-cherry/10 px-4 py-3 text-sm text-cherry-dark">
                                        <TriangleAlert className="mt-0.5 h-4 w-4 shrink-0" />
                                        You are accounting for more units than went out on:{' '}
                                        {computed.over.join(', ')}.
                                    </p>
                                )}

                                <div className="mt-5 grid gap-4 border-t border-cream-200 pt-5 sm:grid-cols-4">
                                    <Field label="Collected on" required error={form.errors.settled_on}>
                                        <Input
                                            type="date"
                                            value={form.data.settled_on}
                                            onChange={(e) => form.setData('settled_on', e.target.value)}
                                        />
                                    </Field>
                                    <Field
                                        label="Cash collected"
                                        hint={`Owed: ${computed.soldValue.toFixed(2)}`}
                                        error={form.errors.amount_collected}
                                    >
                                        <Input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={form.data.amount_collected}
                                            onChange={(e) => form.setData('amount_collected', e.target.value)}
                                            placeholder={computed.soldValue.toFixed(2)}
                                        />
                                    </Field>
                                    <Field label="Method" error={form.errors.method}>
                                        <Select
                                            value={form.data.method}
                                            onChange={(e) => form.setData('method', e.target.value)}
                                        >
                                            <option value="cash">Cash</option>
                                            <option value="gcash">GCash</option>
                                            <option value="qrph">QR Ph</option>
                                            <option value="bank_transfer">Bank transfer</option>
                                        </Select>
                                    </Field>
                                    <Field label="Reference" error={form.errors.reference}>
                                        <Input
                                            value={form.data.reference}
                                            onChange={(e) => form.setData('reference', e.target.value)}
                                        />
                                    </Field>

                                    <Field label="Notes" error={form.errors.notes} className="sm:col-span-4">
                                        <Textarea
                                            rows={2}
                                            value={form.data.notes}
                                            onChange={(e) => form.setData('notes', e.target.value)}
                                            placeholder="Two cups went soft in the heat — written off."
                                        />
                                    </Field>
                                </div>

                                {form.data.is_final && computed.remaining > 0 && (
                                    <p className="mt-3 flex items-start gap-2 rounded-xl bg-caramel-soft/30 px-4 py-3 text-sm text-caramel-dark">
                                        <TriangleAlert className="mt-0.5 h-4 w-4 shrink-0" />
                                        {computed.remaining} units are still unaccounted for. Record them before
                                        closing this batch.
                                    </p>
                                )}

                                <div className="mt-4 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-cream-100 px-4 py-3">
                                    <label className="flex cursor-pointer items-center gap-2.5 text-sm text-chocolate-600">
                                        <input
                                            type="checkbox"
                                            checked={form.data.is_final}
                                            onChange={(e) => form.setData('is_final', e.target.checked)}
                                            className="h-4 w-4 rounded border-cream-300 text-chocolate-700 focus:ring-blush-300"
                                        />
                                        Close this batch after recording
                                    </label>

                                    <div className="flex items-center gap-4">
                                        <span className="text-sm text-chocolate-500">
                                            Owed this collection{' '}
                                            <strong className="font-display text-base text-chocolate-700">
                                                <Money value={computed.soldValue} />
                                            </strong>
                                        </span>
                                        <Button
                                            type="submit"
                                            disabled={
                                                form.processing ||
                                                !computed.anything ||
                                                computed.over.length > 0 ||
                                                (form.data.is_final && computed.remaining > 0)
                                            }
                                        >
                                            <PackageCheck className="h-4 w-4" /> Record collection
                                        </Button>
                                    </div>
                                </div>
                            </form>
                        </Card>
                    )}

                    {/* Collections so far */}
                    {consignment.settlements.length > 0 && (
                        <Card className="card-pad">
                            <h2 className="font-display text-lg font-semibold text-chocolate-700">Collections</h2>

                            <div className="mt-4 space-y-3">
                                {consignment.settlements.map((s) => (
                                    <div
                                        key={s.id}
                                        className="rounded-2xl border border-cream-300 bg-cream-50 p-4"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-3">
                                            <div>
                                                <p className="font-mono text-sm font-semibold text-chocolate-700">
                                                    {s.receipt_number}
                                                </p>
                                                <p className="text-xs text-chocolate-400">
                                                    {s.settled_on} · {s.method_label}
                                                    {s.reference ? ` · ${s.reference}` : ''}
                                                    {s.is_final ? ' · closed the batch' : ''}
                                                </p>
                                            </div>

                                            <div className="flex items-center gap-3">
                                                <span className="text-right">
                                                    <span className="block font-semibold tabular-nums text-success">
                                                        <Money value={s.amount_collected} />
                                                    </span>
                                                    <span className="block text-[11px] text-chocolate-300">
                                                        owed <Money value={s.sold_value} />
                                                    </span>
                                                </span>
                                                <a
                                                    href={route('admin.consignments.settlement.receipt', s.id)}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="rounded-lg p-1.5 text-chocolate-400 transition hover:bg-cream-200 hover:text-chocolate-700"
                                                    title="Print receipt"
                                                >
                                                    <Printer className="h-4 w-4" />
                                                </a>
                                                <a
                                                    href={route('admin.consignments.settlement.receipt', {
                                                        settlement: s.id,
                                                        pdf: 1,
                                                    })}
                                                    className="rounded-lg p-1.5 text-chocolate-400 transition hover:bg-cream-200 hover:text-chocolate-700"
                                                    title="Download PDF"
                                                >
                                                    <Download className="h-4 w-4" />
                                                </a>
                                            </div>
                                        </div>

                                        <ul className="mt-3 grid gap-1 border-t border-cream-300 pt-3 text-xs text-chocolate-500 sm:grid-cols-2">
                                            {s.lines.map((l, i) => (
                                                <li key={i} className="flex justify-between gap-3">
                                                    <span className="truncate">{l.name}</span>
                                                    <span className="shrink-0 tabular-nums">
                                                        {l.sold > 0 && <span className="text-success">{l.sold} sold</span>}
                                                        {l.returned > 0 && <span> · {l.returned} back</span>}
                                                        {l.expired + l.damaged + l.missing > 0 && (
                                                            <span className="text-cherry-dark">
                                                                {' '}
                                                                · {l.expired + l.damaged + l.missing} lost/other
                                                            </span>
                                                        )}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                ))}
                            </div>
                        </Card>
                    )}
                </div>

                {/* Side panel */}
                <div className="space-y-4 xl:sticky xl:top-6">
                    <Card className="overflow-hidden">
                        <div className="bg-choco-fade px-5 py-5 text-cream-100">
                            <p className="text-xs font-semibold uppercase tracking-[0.16em] text-blush-300">
                                Value handed out
                            </p>
                            <p className="mt-1 font-display text-3xl font-semibold">
                                <Money value={consignment.totals.issued_value} />
                            </p>
                            <p className="mt-1 text-sm text-cream-200/60">
                                {consignment.totals.quantity_issued} pcs
                            </p>
                        </div>

                        <dl className="space-y-2 p-5 text-sm">
                            <div className="flex justify-between text-chocolate-500">
                                <dt>Sold</dt>
                                <dd className="tabular-nums">
                                    {consignment.totals.quantity_sold} pcs ·{' '}
                                    <Money value={consignment.totals.sold_value} />
                                </dd>
                            </div>
                            <div className="flex justify-between text-chocolate-500">
                                <dt>Returned in good condition</dt>
                                <dd className="tabular-nums">{consignment.totals.quantity_returned} pcs</dd>
                            </div>
                            <div className="flex justify-between text-cherry-dark">
                                <dt>Expired / damaged / missing / other</dt>
                                <dd className="tabular-nums">
                                    {consignment.totals.quantity_expired +
                                        consignment.totals.quantity_damaged +
                                        consignment.totals.quantity_missing}{' '}
                                    pcs
                                </dd>
                            </div>
                            <div className="flex justify-between border-t border-cream-200 pt-2.5 text-cherry-dark">
                                <dt>Loss at cost</dt>
                                <dd className="tabular-nums">
                                    <Money value={consignment.totals.loss_value} />
                                </dd>
                            </div>
                            <div className="flex justify-between font-semibold text-success">
                                <dt>Cash collected</dt>
                                <dd className="tabular-nums">
                                    <Money value={consignment.totals.amount_collected} />
                                </dd>
                            </div>
                            <div
                                className={clsx(
                                    'flex justify-between rounded-xl px-3 py-2.5 font-display text-base font-semibold',
                                    consignment.totals.amount_due > 0
                                        ? 'bg-caramel-soft/25 text-caramel-dark'
                                        : 'bg-success-light text-success',
                                )}
                            >
                                <dt>Still to collect</dt>
                                <dd className="tabular-nums">
                                    <Money value={consignment.totals.amount_due} />
                                </dd>
                            </div>
                        </dl>
                    </Card>

                    <Card className="card-pad">
                        <h2 className="font-display text-base font-semibold text-chocolate-700">Seller</h2>
                        <p className="mt-2 font-medium text-chocolate-700">
                            {seller.business_name || seller.name}
                        </p>
                        {seller.business_name && seller.business_name !== seller.name && (
                            <p className="text-sm text-chocolate-400">{seller.name}</p>
                        )}
                        <p className="font-mono text-xs text-chocolate-300">{seller.code}</p>
                        <p className="mt-2 flex items-center gap-2 text-sm text-chocolate-500">
                            <Phone className="h-4 w-4 text-blush-500" /> {seller.phone}
                        </p>

                        <ButtonLink
                            href={route('admin.resellers.show', seller.id)}
                            variant="ghost"
                            className="mt-4 w-full text-xs"
                        >
                            Open profile
                        </ButtonLink>
                    </Card>

                    {open && (
                        <Card className="card-pad">
                            <h2 className="font-display text-base font-semibold text-chocolate-700">
                                Cancel this batch
                            </h2>
                            <p className="mt-1 text-sm text-chocolate-400">
                                Restores tracked stock for {consignment.totals.outstanding} outstanding pcs.
                            </p>
                            <Button
                                variant="danger"
                                className="mt-3 w-full"
                                onClick={() => {
                                    if (window.confirm('Cancel this consignment and restore its tracked stock?')) {
                                        router.post(route('admin.consignments.cancel', consignment.number), {}, {
                                            preserveScroll: true,
                                        });
                                    }
                                }}
                            >
                                <Ban className="h-4 w-4" /> Cancel consignment
                            </Button>
                        </Card>
                    )}

                    {!open && (
                        <Card className="card-pad text-center">
                            <HandCoins className="mx-auto h-8 w-8 text-chocolate-200" />
                            <p className="mt-2 text-sm text-chocolate-400">
                                {consignment.status === 'cancelled'
                                    ? 'This batch was cancelled and its tracked stock was restored.'
                                    : `Closed ${consignment.settled_at}.`}
                            </p>
                        </Card>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
