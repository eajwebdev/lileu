import { Link, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import clsx from 'clsx';
import { ArrowLeft, HandCoins, Minus, Plus, Search, Trash2 } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Button, Card, EmptyState, Field, Input, Money, Select, Textarea } from '@/Components/Lileu/ui';
import { ProductImage } from '@/Components/Lileu/product';

export default function Create({ sellers, products }) {
    const [term, setTerm] = useState('');
    const [cart, setCart] = useState({});

    const { data, setData, post, processing, errors, transform } = useForm({
        reseller_id: '',
        items: [],
        issued_on: new Date().toISOString().slice(0, 10),
        due_on: '',
        notes: '',
    });

    const visible = useMemo(
        () =>
            products.filter(
                (p) =>
                    !term ||
                    p.name.toLowerCase().includes(term.toLowerCase()) ||
                    p.sku.toLowerCase().includes(term.toLowerCase()),
            ),
        [products, term],
    );

    const lines = useMemo(
        () =>
            Object.entries(cart)
                .filter(([, row]) => row.quantity > 0)
                .map(([id, row]) => {
                    const product = products.find((p) => String(p.id) === id);
                    const unitPrice = row.unit_price === '' ? product.unit_price : Number(row.unit_price);

                    return { product, quantity: row.quantity, unitPrice, lineTotal: unitPrice * row.quantity };
                }),
        [cart, products],
    );

    const issuedValue = lines.reduce((sum, l) => sum + l.lineTotal, 0);
    const issuedQty = lines.reduce((sum, l) => sum + l.quantity, 0);
    const potentialRetail = lines.reduce((sum, l) => sum + l.product.retail_price * l.quantity, 0);

    const setQty = (product, quantity) =>
        setCart((prev) => {
            const next = { ...prev };
            const clamped = Math.max(0, quantity);

            if (clamped === 0) delete next[product.id];
            else next[product.id] = { quantity: clamped, unit_price: prev[product.id]?.unit_price ?? '' };

            return next;
        });

    const setPrice = (product, value) =>
        setCart((prev) => ({
            ...prev,
            [product.id]: { quantity: prev[product.id]?.quantity ?? 0, unit_price: value },
        }));

    const submit = (e) => {
        e.preventDefault();

        transform((form) => ({
            ...form,
            items: lines.map((l) => ({
                product_id: l.product.id,
                quantity: l.quantity,
                unit_price: l.unitPrice,
            })),
        }));

        post(route('admin.consignments.store'));
    };

    return (
        <AdminLayout
            title="Issue a consignment"
            subtitle="Hand stock to a seller now, settle the cash and returns later."
            action={
                <Link
                    href={route('admin.consignments.index')}
                    className="inline-flex items-center gap-1.5 text-sm font-medium text-chocolate-400 transition hover:text-chocolate-700"
                >
                    <ArrowLeft className="h-4 w-4" /> All consignments
                </Link>
            }
        >
            <form onSubmit={submit} className="grid gap-5 xl:grid-cols-[1.6fr_1fr] xl:items-start">
                <div>
                    <div className="relative mb-4">
                        <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-chocolate-300" />
                        <Input
                            value={term}
                            onChange={(e) => setTerm(e.target.value)}
                            placeholder="Search product or SKU…"
                            className="pl-10"
                        />
                    </div>

                    {visible.length === 0 ? (
                        <EmptyState icon={HandCoins} title="Nothing matches that" />
                    ) : (
                        <div className="grid gap-3 sm:grid-cols-2">
                            {visible.map((product) => {
                                const row = cart[product.id];
                                const qty = row?.quantity ?? 0;

                                return (
                                    <div
                                        key={product.id}
                                        className={clsx(
                                            'flex gap-3 rounded-2xl border p-3 transition',
                                            qty > 0
                                                ? 'border-blush-300 bg-blush-50 shadow-soft'
                                                : 'border-cream-300/70 bg-vanilla',
                                        )}
                                    >
                                        <div className="h-20 w-20 shrink-0 overflow-hidden rounded-xl bg-cream-200">
                                            <ProductImage product={product} />
                                        </div>

                                        <div className="flex min-w-0 flex-1 flex-col">
                                            <p className="truncate font-semibold text-chocolate-700">
                                                {product.name}
                                            </p>
                                            <p className="text-xs text-chocolate-400">
                                                {product.stock} in stock · retail{' '}
                                                <Money value={product.retail_price} decimals={0} />
                                            </p>

                                            <div className="mt-auto flex items-center gap-2 pt-2">
                                                <button
                                                    type="button"
                                                    onClick={() => setQty(product, qty - 1)}
                                                    disabled={qty === 0}
                                                    className="flex h-8 w-8 items-center justify-center rounded-lg border border-cream-300 bg-vanilla text-chocolate-600 transition hover:bg-cream-100 disabled:opacity-40"
                                                    aria-label={`Remove one ${product.name}`}
                                                >
                                                    <Minus className="h-4 w-4" />
                                                </button>
                                                <input
                                                    type="number"
                                                    min={0}
                                                    max={product.stock}
                                                    value={qty}
                                                    onChange={(e) => setQty(product, Number(e.target.value))}
                                                    className="h-8 w-16 rounded-lg border-cream-300 bg-vanilla text-center text-sm tabular-nums focus:border-blush-400 focus:ring-blush-200"
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() => setQty(product, qty + 1)}
                                                    disabled={qty >= product.stock}
                                                    className="flex h-8 w-8 items-center justify-center rounded-lg border border-cream-300 bg-vanilla text-chocolate-600 transition hover:bg-cream-100 disabled:opacity-40"
                                                    aria-label={`Add one ${product.name}`}
                                                >
                                                    <Plus className="h-4 w-4" />
                                                </button>

                                                {qty > 0 && (
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        value={row?.unit_price ?? ''}
                                                        onChange={(e) => setPrice(product, e.target.value)}
                                                        placeholder={String(product.unit_price)}
                                                        title="Price the seller remits per unit"
                                                        className="ml-auto h-8 w-20 rounded-lg border-cream-300 bg-vanilla text-right text-sm tabular-nums focus:border-blush-400 focus:ring-blush-200"
                                                    />
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>

                <div className="space-y-4 xl:sticky xl:top-6">
                    <Card className="card-pad">
                        <h2 className="font-display text-lg font-semibold text-chocolate-700">Batch details</h2>

                        <div className="mt-4 space-y-4">
                            <Field label="Seller" required error={errors.reseller_id}>
                                <Select
                                    value={data.reseller_id}
                                    onChange={(e) => setData('reseller_id', e.target.value)}
                                >
                                    <option value="">Choose a seller…</option>
                                    {sellers.map((seller) => (
                                        <option key={seller.id} value={seller.id}>
                                            {seller.label} · {seller.code}
                                            {seller.engagement === 'reseller' ? ' (reseller)' : ''}
                                        </option>
                                    ))}
                                </Select>
                            </Field>

                            <div className="grid grid-cols-2 gap-3">
                                <Field label="Issued on" required error={errors.issued_on}>
                                    <Input
                                        type="date"
                                        value={data.issued_on}
                                        onChange={(e) => setData('issued_on', e.target.value)}
                                    />
                                </Field>
                                <Field
                                    label="Collect by"
                                    hint="Usually today or tomorrow."
                                    error={errors.due_on}
                                >
                                    <Input
                                        type="date"
                                        value={data.due_on}
                                        onChange={(e) => setData('due_on', e.target.value)}
                                    />
                                </Field>
                            </div>

                            <Field label="Notes" error={errors.notes}>
                                <Textarea
                                    rows={2}
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                    placeholder="Selling at the school canteen until 4 PM."
                                />
                            </Field>
                        </div>
                    </Card>

                    <Card className="card-pad">
                        <h2 className="font-display text-lg font-semibold text-chocolate-700">Going out</h2>

                        {lines.length === 0 ? (
                            <p className="mt-3 rounded-xl border border-dashed border-cream-300 px-4 py-6 text-center text-sm text-chocolate-400">
                                Pick the products to hand over.
                            </p>
                        ) : (
                            <ul className="mt-3 divide-y divide-cream-200">
                                {lines.map((line) => (
                                    <li key={line.product.id} className="flex items-center gap-3 py-2.5">
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium text-chocolate-700">
                                                {line.product.name}
                                            </p>
                                            <p className="text-xs text-chocolate-400">
                                                {line.quantity} × <Money value={line.unitPrice} />
                                            </p>
                                        </div>
                                        <span className="text-sm font-semibold tabular-nums text-chocolate-700">
                                            <Money value={line.lineTotal} />
                                        </span>
                                        <button
                                            type="button"
                                            onClick={() => setQty(line.product, 0)}
                                            className="rounded-lg p-1.5 text-chocolate-300 transition hover:bg-cherry/10 hover:text-cherry"
                                            aria-label={`Remove ${line.product.name}`}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}

                        {errors.items && <p className="mt-2 text-xs font-medium text-cherry">{errors.items}</p>}

                        <dl className="mt-4 space-y-1.5 border-t border-cream-300 pt-4 text-sm">
                            <div className="flex justify-between text-chocolate-500">
                                <dt>Units out</dt>
                                <dd className="tabular-nums">{issuedQty}</dd>
                            </div>
                            <div className="flex justify-between font-display text-base font-semibold text-chocolate-700">
                                <dt>Value we are owed if all sells</dt>
                                <dd className="tabular-nums">
                                    <Money value={issuedValue} />
                                </dd>
                            </div>
                            <div className="flex justify-between text-success">
                                <dt>Seller keeps</dt>
                                <dd className="tabular-nums">
                                    <Money value={potentialRetail - issuedValue} />
                                </dd>
                            </div>
                        </dl>

                        <Button
                            type="submit"
                            disabled={processing || lines.length === 0 || !data.reseller_id}
                            className="mt-4 w-full py-3 text-base"
                        >
                            <HandCoins className="h-4 w-4" /> Issue consignment
                        </Button>

                        <p className="mt-3 text-center text-xs leading-relaxed text-chocolate-400">
                            Stock leaves inventory now. Whatever comes back in good condition is returned to the
                            shelf when you settle.
                        </p>
                    </Card>
                </div>
            </form>
        </AdminLayout>
    );
}
