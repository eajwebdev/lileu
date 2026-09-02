import { router } from '@inertiajs/react';
import { useState } from 'react';
import clsx from 'clsx';
import { Bar, BarChart, CartesianGrid, Cell, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { CalendarRange } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Button, Card, Field, Input, Money, peso } from '@/Components/Lileu/ui';

const METHOD_COLORS = ['#3B2A22', '#EFAFB8', '#D7973E', '#2F7D4A', '#C71827'];
const METHOD_LABELS = {
    qrph: 'QR Ph',
    cash: 'Cash',
    gcash: 'GCash',
    bank_transfer: 'Bank transfer',
    manual: 'Manual',
    card: 'Card',
};

function SummaryTile({ label, value, tone = 'chocolate', hint }) {
    const tones = {
        chocolate: 'text-chocolate-700',
        success: 'text-success',
        caramel: 'text-caramel-dark',
        blush: 'text-blush-600',
    };

    return (
        <Card className="card-pad">
            <p className="text-sm text-chocolate-400">{label}</p>
            <p className={clsx('mt-1 font-display text-2xl font-semibold', tones[tone])}>
                <Money value={value} decimals={0} />
            </p>
            {hint && <p className="mt-0.5 text-xs text-chocolate-300">{hint}</p>}
        </Card>
    );
}

export default function Index({ range, summary, byPaymentMethod, topResellers, productMix }) {
    const [from, setFrom] = useState(range.from);
    const [to, setTo] = useState(range.to);

    return (
        <AdminLayout
            title="Reports"
            subtitle="Sales, spend and channel performance for any window."
            action={
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get(route('admin.reports.index'), { from, to }, { preserveState: true });
                    }}
                    className="flex flex-wrap items-end gap-2"
                >
                    <Field label="From">
                        <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="w-auto" />
                    </Field>
                    <Field label="To">
                        <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="w-auto" />
                    </Field>
                    <Button type="submit">
                        <CalendarRange className="h-4 w-4" /> Apply
                    </Button>
                </form>
            }
        >
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <SummaryTile
                    label="Gross sales"
                    value={summary.gross_sales}
                    hint={`POS ${peso(summary.pos_sales)} · Reseller ${peso(summary.reseller_collected)}`}
                />
                <SummaryTile label="Purchases" value={summary.purchases} tone="caramel" />
                <SummaryTile label="Expenses" value={summary.expenses} tone="blush" />
                <SummaryTile label="Net profit" value={summary.net_profit} tone="success" />
            </div>

            <div className="mt-4 grid gap-4 sm:grid-cols-2">
                <Card className="card-pad">
                    <p className="text-sm text-chocolate-400">Reseller orders placed</p>
                    <p className="mt-1 font-display text-2xl font-semibold text-chocolate-700">
                        {summary.orders_placed}
                    </p>
                </Card>
                <Card className="card-pad">
                    <p className="text-sm text-chocolate-400">Receivables outstanding (all time)</p>
                    <p className="mt-1 font-display text-2xl font-semibold text-caramel-dark">
                        <Money value={summary.receivables} />
                    </p>
                </Card>
            </div>

            <div className="mt-6 grid gap-4 lg:grid-cols-[1fr_1.4fr]">
                <Card className="card-pad">
                    <h2 className="font-display text-lg font-semibold text-chocolate-700">Collections by method</h2>

                    {byPaymentMethod.length === 0 ? (
                        <p className="mt-6 rounded-xl border border-dashed border-cream-300 px-4 py-10 text-center text-sm text-chocolate-400">
                            No payments in this window.
                        </p>
                    ) : (
                        <>
                            <div className="mt-4 h-56">
                                <ResponsiveContainer width="100%" height="100%">
                                    <PieChart>
                                        <Pie
                                            data={byPaymentMethod}
                                            dataKey="amount"
                                            nameKey="method"
                                            innerRadius={52}
                                            outerRadius={82}
                                            paddingAngle={2}
                                        >
                                            {byPaymentMethod.map((entry, i) => (
                                                <Cell key={entry.method} fill={METHOD_COLORS[i % METHOD_COLORS.length]} />
                                            ))}
                                        </Pie>
                                        <Tooltip
                                            formatter={(value) => peso(value)}
                                            contentStyle={{
                                                borderRadius: 12,
                                                border: '1px solid #EBD8C3',
                                                background: '#FFFDFC',
                                                fontSize: 12,
                                            }}
                                        />
                                    </PieChart>
                                </ResponsiveContainer>
                            </div>

                            <ul className="mt-2 space-y-1.5">
                                {byPaymentMethod.map((entry, i) => (
                                    <li key={entry.method} className="flex items-center justify-between text-sm">
                                        <span className="flex items-center gap-2 text-chocolate-500">
                                            <span
                                                className="h-2.5 w-2.5 rounded-full"
                                                style={{ background: METHOD_COLORS[i % METHOD_COLORS.length] }}
                                            />
                                            {METHOD_LABELS[entry.method] ?? entry.method}
                                            <span className="text-chocolate-300">({entry.count})</span>
                                        </span>
                                        <span className="font-semibold tabular-nums text-chocolate-700">
                                            <Money value={entry.amount} />
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </>
                    )}
                </Card>

                <Card className="card-pad">
                    <h2 className="font-display text-lg font-semibold text-chocolate-700">Product mix</h2>
                    <p className="text-sm text-chocolate-400">Units ordered by resellers in this window.</p>

                    <div className="mt-4 h-72">
                        <ResponsiveContainer width="100%" height="100%">
                            <BarChart data={productMix} layout="vertical" margin={{ left: 0, right: 16 }}>
                                <CartesianGrid stroke="#EBD8C3" strokeDasharray="3 3" horizontal={false} />
                                <XAxis type="number" tick={{ fontSize: 11, fill: '#8A6B5A' }} axisLine={false} tickLine={false} />
                                <YAxis
                                    type="category"
                                    dataKey="name"
                                    tick={{ fontSize: 11, fill: '#8A6B5A' }}
                                    axisLine={false}
                                    tickLine={false}
                                    width={130}
                                />
                                <Tooltip
                                    cursor={{ fill: '#FBF5EC' }}
                                    contentStyle={{
                                        borderRadius: 12,
                                        border: '1px solid #EBD8C3',
                                        background: '#FFFDFC',
                                        fontSize: 12,
                                    }}
                                />
                                <Bar dataKey="qty" name="Units" fill="#EFAFB8" radius={[0, 6, 6, 0]} barSize={14} />
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                </Card>
            </div>

            <Card className="card-pad mt-6">
                <h2 className="font-display text-lg font-semibold text-chocolate-700">Top resellers</h2>

                <div className="mt-4 overflow-x-auto">
                    <table className="table-lileu min-w-full">
                        <thead>
                            <tr>
                                <th className="w-10">#</th>
                                <th>Reseller</th>
                                <th>Code</th>
                                <th className="text-right">Ordered value</th>
                            </tr>
                        </thead>
                        <tbody>
                            {topResellers.map((reseller, i) => (
                                <tr key={reseller.code}>
                                    <td className="text-chocolate-300">{i + 1}</td>
                                    <td className="font-medium text-chocolate-700">{reseller.name}</td>
                                    <td className="font-mono text-xs text-chocolate-300">{reseller.code}</td>
                                    <td className="text-right font-semibold tabular-nums text-chocolate-700">
                                        <Money value={reseller.ordered} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Card>
        </AdminLayout>
    );
}
