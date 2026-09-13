import { Link, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
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

    // Keep the tablet workspace fixed; only long product and batch lists scroll.
    useEffect(() => {
        const bodyOverflow = document.body.style.overflow;
        const htmlOverflow = document.documentElement.style.overflow;

        document.body.style.overflow = 'hidden';
        document.documentElement.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = bodyOverflow;
            document.documentElement.style.overflow = htmlOverflow;
        };
    }, []);

    const visible = useMemo(
        () =>
            products.filter(
                (p) =>
                    !term ||
                    p.label.toLowerCase().includes(term.toLowerCase()) ||
                    (p.sku ?? '').toLowerCase().includes(term.toLowerCase()),
            ),
        [products, term],
    );

    // A consignee is a reseller, so they take stock at the rate they buy at.
    // A flavour's own reseller price always wins; a rate negotiated against
    // the product only applies where there are no flavours to contradict it.
    const seller = sellers.find((s) => String(s.id) === String(data.reseller_id));

    const rateFor = (product) => {
        if (product.variant_id) return product.reseller_price;

        return seller?.prices?.[product.product_id] ?? product.reseller_price;
    };

    const lines = useMemo(
        () =>
            Object.entries(cart)
                .filter(([, row]) => row.quantity > 0)
                .map(([key, row]) => {
                    const product = products.find((p) => p.key === key);

                    if (!product) return null;

                    const unitPrice = row.unit_price === '' ? rateFor(product) : Number(row.unit_price);

                    return {
                        product,
                        quantity: row.quantity,
                        unitPrice,
                        lineTotal: unitPrice * row.quantity,
                    };
                })
                .filter(Boolean),
        [cart, products, seller],
    );

    const issuedValue = lines.reduce((sum, l) => sum + l.lineTotal, 0);
    const issuedQty = lines.reduce((sum, l) => sum + l.quantity, 0);
    const potentialRetail = lines.reduce((sum, l) => sum + l.product.retail_price * l.quantity, 0);

    const setQty = (product, quantity) =>
        setCart((prev) => {
            const next = { ...prev };
            const maximum = !product.is_available ? 0 : product.tracks_stock ? product.stock : 100000;
            const clamped = Math.min(maximum, Math.max(0, quantity));

            if (clamped === 0) delete next[product.key];
            else
                next[product.key] = {
                    quantity: clamped,
                    unit_price: prev[product.key]?.unit_price ?? '',
                };

            return next;
        });

    const setPrice = (product, value) =>
        setCart((prev) => ({
            ...prev,
            [product.key]: {
                quantity: prev[product.key]?.quantity ?? 0,
                unit_price: value,
            },
        }));

    const submit = (e) => {
        e.preventDefault();

        transform((form) => ({
            ...form,
            items: lines.map((l) => ({
                product_id: l.product.product_id,
                product_variant_id: l.product.variant_id,
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
            workspace
            action={
                <Link
                    href={route('admin.consignments.index')}
                    className="inline-flex items-center gap-1.5 text-sm font-medium text-chocolate-400 transition hover:text-chocolate-700"
                >
                    <ArrowLeft className="h-4 w-4" /> All consignments
                </Link>
            }
        >
            <form
                onSubmit={submit}
                className="grid min-h-0 flex-1 grid-rows-[minmax(0,.8fr)_minmax(0,1.2fr)] gap-3 overflow-hidden md:grid-cols-[minmax(0,1fr)_21rem] md:grid-rows-1 xl:grid-cols-[minmax(0,1.6fr)_23rem]"
            >
                <section className="flex min-h-0 min-w-0 flex-col overflow-hidden">
                    <div className="mb-2.5 flex shrink-0 items-center gap-2">
                        <div className="relative min-w-0 flex-1">
                            <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-chocolate-300" />
                            <Input
                                value={term}
                                onChange={(e) => setTerm(e.target.value)}
                                placeholder="Search product or SKU…"
                                className="h-11 pl-10 text-base"
                            />
                        </div>
                        <span className="hidden shrink-0 rounded-xl bg-vanilla px-3 py-2.5 text-xs font-semibold text-chocolate-400 sm:inline-flex">
                            {visible.length} products
                        </span>
                    </div>

                    {visible.length === 0 ? (
                        <div className="flex min-h-0 flex-1 items-center justify-center overflow-hidden">
                            <EmptyState icon={HandCoins} title="Nothing matches that" />
                        </div>
                    ) : (
                        <div className="grid min-h-0 flex-1 auto-rows-max grid-cols-1 gap-2 overflow-y-auto overscroll-contain pr-1 pb-2 xl:grid-cols-2">
                            {visible.map((product) => {
                                const row = cart[product.key];
                                const qty = row?.quantity ?? 0;

                                return (
                                    <div
                                        key={product.key}
                                        className={clsx(
                                            'flex min-h-24 gap-2.5 rounded-2xl border p-2.5 transition',
                                            !product.is_available
                                                ? 'border-cream-300/70 bg-cream-100 opacity-60'
                                                : qty > 0
                                                  ? 'border-blush-300 bg-blush-50 shadow-soft'
                                                  : 'border-cream-300/70 bg-vanilla',
                                        )}
                                    >
                                        <div className="h-16 w-16 shrink-0 overflow-hidden rounded-xl bg-cream-200 sm:h-18 sm:w-18">
                                            <ProductImage product={product} />
                                        </div>

                                        <div className="flex min-w-0 flex-1 flex-col">
                                            <p className="truncate font-semibold text-chocolate-700">{product.label}</p>
                                            <p className="text-xs text-chocolate-400">
                                                {!product.is_available
                                                    ? 'Unavailable'
                                                    : product.tracks_stock
                                                      ? `${product.stock} in stock`
                                                      : 'Made to order'}{' '}
                                                · retail{' '}
                                                <Money value={product.retail_price} decimals={0} />
                                            </p>

                                            <div className="mt-auto flex items-center gap-1.5 pt-1.5">
                                                <button
                                                    type="button"
                                                    onClick={() => setQty(product, qty - 1)}
                                                    disabled={qty === 0}
                                                    className="flex h-9 w-9 touch-manipulation items-center justify-center rounded-lg border border-cream-300 bg-vanilla text-chocolate-600 transition hover:bg-cream-100 active:scale-95 disabled:opacity-40"
                                                    aria-label={`Remove one ${product.label}`}
                                                >
                                                    <Minus className="h-4 w-4" />
                                                </button>
                                                <input
                                                    type="number"
                                                    min={0}
                                                    max={
                                                        !product.is_available
                                                            ? 0
                                                            : product.tracks_stock
                                                              ? product.stock
                                                              : 100000
                                                    }
                                                    value={qty}
                                                    onChange={(e) => setQty(product, Number(e.target.value))}
                                                    className="h-9 w-14 rounded-lg border-cream-300 bg-vanilla text-center text-sm tabular-nums focus:border-blush-400 focus:ring-blush-200"
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() => setQty(product, qty + 1)}
                                                    disabled={
                                                        !product.is_available ||
                                                        (product.tracks_stock && qty >= product.stock)
                                                    }
                                                    className="flex h-9 w-9 touch-manipulation items-center justify-center rounded-lg border border-cream-300 bg-vanilla text-chocolate-600 transition hover:bg-cream-100 active:scale-95 disabled:opacity-40"
                                                    aria-label={`Add one ${product.label}`}
                                                >
                                                    <Plus className="h-4 w-4" />
                                                </button>

                                                {qty > 0 && (
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        value={row?.unit_price ?? ''}
                                                        onChange={(e) => setPrice(product, e.target.value)}
                                                        placeholder={String(rateFor(product))}
                                                        title="Price the seller remits per unit"
                                                        className="ml-auto h-9 min-w-0 w-18 rounded-lg border-cream-300 bg-vanilla text-right text-sm tabular-nums focus:border-blush-400 focus:ring-blush-200"
                                                    />
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </section>

                <aside className="flex min-h-0 flex-col gap-3 overflow-y-auto overscroll-contain md:overflow-hidden">
                    <Card className="shrink-0 p-3 sm:p-4">
                        <div className="flex items-center justify-between gap-2">
                            <h2 className="font-display text-base font-semibold text-chocolate-700">Batch details</h2>
                            <span className="text-xs text-chocolate-300">Who gets it and when</span>
                        </div>

                        <div className="mt-2.5 space-y-2.5">
                            <Field label="Seller" required error={errors.reseller_id}>
                                <Select value={data.reseller_id} onChange={(e) => setData('reseller_id', e.target.value)}>
                                    <option value="">Choose a seller…</option>
                                    {sellers.map((option) => (
                                        <option key={option.id} value={option.id}>
                                            {/* A business name never hides the person taking the stock. */}
                                            {[option.label, option.person, option.code].filter(Boolean).join(' · ')}
                                            {option.engagement === 'reseller' ? ' (reseller)' : ''}
                                        </option>
                                    ))}
                                </Select>
                            </Field>

                            {seller && (
                                <p className="-mt-1 text-xs text-chocolate-400">
                                    Handing to{' '}
                                    <span className="font-medium text-chocolate-600">
                                        {seller.person || seller.name}
                                    </span>
                                    {seller.phone ? ` · ${seller.phone}` : ''}
                                </p>
                            )}

                            <div className="grid grid-cols-2 gap-2">
                                <Field label="Issued on" required error={errors.issued_on}>
                                    <Input type="date" value={data.issued_on} onChange={(e) => setData('issued_on', e.target.value)} />
                                </Field>
                                <Field label="Collect by" error={errors.due_on}>
                                    <Input type="date" value={data.due_on} onChange={(e) => setData('due_on', e.target.value)} />
                                </Field>
                            </div>

                            <Field label="Notes" error={errors.notes}>
                                <Textarea
                                    rows={1}
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                    placeholder="Selling at the school canteen until 4 PM."
                                />
                            </Field>
                        </div>
                    </Card>

                    <Card className="flex min-h-0 flex-1 flex-col overflow-hidden p-3 sm:p-4">
                        <div className="flex shrink-0 items-center justify-between gap-3">
                            <h2 className="font-display text-base font-semibold text-chocolate-700">Going out</h2>
                            <span className="rounded-lg bg-cream-200 px-2 py-1 text-xs font-semibold tabular-nums text-chocolate-500">{issuedQty} pcs</span>
                        </div>

                        {lines.length === 0 ? (
                            <p className="mt-2 flex min-h-0 flex-1 items-center justify-center rounded-xl border border-dashed border-cream-300 px-4 text-center text-sm text-chocolate-400">
                                Pick the products to hand over.
                            </p>
                        ) : (
                            <ul className="mt-2 min-h-0 flex-1 divide-y divide-cream-200 overflow-y-auto overscroll-contain pr-1">
                                {lines.map((line) => (
                                    <li key={line.product.key} className="flex items-center gap-2 py-2">
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium text-chocolate-700">{line.product.label}</p>
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
                                            aria-label={`Remove ${line.product.label}`}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}

                        {errors.items && <p className="mt-2 text-xs font-medium text-cherry">{errors.items}</p>}

                        <dl className="mt-2 shrink-0 space-y-1 border-t border-cream-300 pt-2 text-sm">
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
                            className="mt-2.5 w-full shrink-0 py-3 text-base"
                        >
                            <HandCoins className="h-4 w-4" /> Issue consignment
                        </Button>

                        <p className="mt-2 shrink-0 text-center text-[10px] leading-tight text-chocolate-300">
                            Stocked items change inventory; made-to-order items do not.
                        </p>
                    </Card>
                </aside>
            </form>
        </AdminLayout>
    );
}
