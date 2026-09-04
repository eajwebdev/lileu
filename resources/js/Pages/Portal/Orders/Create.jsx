import { useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import clsx from 'clsx';
import { Loader2, Minus, Plus, Search, ShoppingBag, Trash2 } from 'lucide-react';
import PortalLayout from '@/Layouts/PortalLayout';
import { Button, EmptyState, Field, Input, Money, Textarea } from '@/Components/Lileu/ui';
import { ProductImage } from '@/Components/Lileu/product';

export default function Create({ products, reseller }) {
    const [term, setTerm] = useState('');
    const [cart, setCart] = useState({});

    const { data, setData, post, processing, errors, transform } = useForm({
        items: [],
        fulfillment_type: 'pickup',
        delivery_address: reseller.address ?? '',
        date_needed: '',
        time_needed: '',
        notes: '',
    });

    const visible = useMemo(
        () =>
            products.filter(
                (p) =>
                    !term ||
                    p.label.toLowerCase().includes(term.toLowerCase()) ||
                    p.sku.toLowerCase().includes(term.toLowerCase()),
            ),
        [products, term],
    );

    const lines = useMemo(
        () =>
            Object.entries(cart)
                .filter(([, qty]) => qty > 0)
                .map(([id, qty]) => {
                    const product = products.find((p) => p.key === id);

                    return { product, quantity: qty, lineTotal: (product?.unit_price ?? 0) * qty };
                })
                .filter((l) => l.product),
        [cart, products],
    );

    const subtotal = lines.reduce((sum, l) => sum + l.lineTotal, 0);
    const discount = (subtotal * reseller.discount_percent) / 100;
    const total = subtotal - discount;
    const downpayment = (total * reseller.downpayment_percent) / 100;

    const setQty = (product, qty) =>
        setCart((prev) => {
            const next = { ...prev };
            const clamped = Math.max(0, qty);

            if (clamped === 0) delete next[product.key];
            else next[product.key] = clamped;

            return next;
        });

    const submit = (e) => {
        e.preventDefault();

        // The cart lives in local state; fold it into the payload at submit time.
        transform((form) => ({
            ...form,
            items: lines.map((l) => ({
                product_id: l.product.product_id,
                product_variant_id: l.product.variant_id,
                quantity: l.quantity,
            })),
        }));

        post(route('portal.orders.store'));
    };

    return (
        <PortalLayout title="New order" subtitle="Pick your flavours, then choose when you need them.">
            <form onSubmit={submit} className="grid gap-6 lg:grid-cols-[1.55fr_1fr] lg:items-start">
                {/* Catalog */}
                <div>
                    <div className="relative mb-4">
                        <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-chocolate-300" />
                        <Input
                            value={term}
                            onChange={(e) => setTerm(e.target.value)}
                            placeholder="Search your catalog…"
                            className="pl-10"
                        />
                    </div>

                    {visible.length === 0 ? (
                        <EmptyState
                            icon={ShoppingBag}
                            title="Nothing matches that"
                            description="Try another flavour name or clear the search."
                        />
                    ) : (
                        <div className="grid gap-3 sm:grid-cols-2">
                            {visible.map((product) => {
                                const qty = cart[product.key] ?? 0;
                                const selected = qty > 0;

                                return (
                                    <div
                                        key={product.key}
                                        className={clsx(
                                            'flex gap-3 rounded-2xl border p-3 transition',
                                            selected
                                                ? 'border-blush-300 bg-blush-50 shadow-soft'
                                                : 'border-cream-300/70 bg-vanilla',
                                        )}
                                    >
                                        <div className="h-20 w-20 shrink-0 overflow-hidden rounded-xl bg-cream-200">
                                            <ProductImage product={product} />
                                        </div>

                                        <div className="flex min-w-0 flex-1 flex-col">
                                            <p className="truncate font-semibold text-chocolate-700">
                                                {product.label}
                                            </p>
                                            <p className="text-xs text-chocolate-400">
                                                <Money value={product.unit_price} /> · retail{' '}
                                                <Money value={product.retail_price} decimals={0} />
                                            </p>
                                            {product.made_to_order ? (
                                                <p className="mt-0.5 text-xs font-medium text-blush-dark">
                                                    Made to order
                                                </p>
                                            ) : !product.in_stock ? (
                                                <p className="mt-0.5 text-xs font-medium text-cherry">
                                                    Currently out of stock
                                                </p>
                                            ) : null}

                                            <div className="mt-auto flex items-center gap-2 pt-2">
                                                <button
                                                    type="button"
                                                    onClick={() => setQty(product, qty - 1)}
                                                    className="flex h-8 w-8 items-center justify-center rounded-lg border border-cream-300 bg-vanilla text-chocolate-600 transition hover:bg-cream-100 disabled:opacity-40"
                                                    disabled={qty === 0}
                                                    aria-label={`Remove one ${product.label}`}
                                                >
                                                    <Minus className="h-4 w-4" />
                                                </button>
                                                <input
                                                    type="number"
                                                    min={0}
                                                    value={qty}
                                                    onChange={(e) => setQty(product, Number(e.target.value))}
                                                    className="h-8 w-16 rounded-lg border-cream-300 bg-vanilla text-center text-sm tabular-nums focus:border-blush-400 focus:ring-blush-200"
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() => setQty(product, qty === 0 ? product.min_qty : qty + 1)}
                                                    className="flex h-8 w-8 items-center justify-center rounded-lg border border-cream-300 bg-vanilla text-chocolate-600 transition hover:bg-cream-100"
                                                    aria-label={`Add one ${product.label}`}
                                                >
                                                    <Plus className="h-4 w-4" />
                                                </button>
                                                <span className="ml-auto text-xs text-chocolate-300">
                                                    min {product.min_qty}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>

                {/* Cart + schedule */}
                <div className="space-y-4 lg:sticky lg:top-24">
                    <div className="card card-pad">
                        <h2 className="font-display text-lg font-semibold text-chocolate-700">Your order</h2>

                        {lines.length === 0 ? (
                            <p className="mt-3 rounded-xl border border-dashed border-cream-300 px-4 py-6 text-center text-sm text-chocolate-400">
                                Add a flavour to get started.
                            </p>
                        ) : (
                            <ul className="mt-3 divide-y divide-cream-200">
                                {lines.map((line) => (
                                    <li key={line.product.key} className="flex items-center gap-3 py-2.5">
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium text-chocolate-700">
                                                {line.product.label}
                                            </p>
                                            <p className="text-xs text-chocolate-400">
                                                {line.quantity} × <Money value={line.product.unit_price} />
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

                        <dl className="mt-4 space-y-1.5 border-t border-cream-300 pt-4 text-sm">
                            <div className="flex justify-between text-chocolate-500">
                                <dt>Subtotal</dt>
                                <dd className="tabular-nums">
                                    <Money value={subtotal} />
                                </dd>
                            </div>
                            {reseller.discount_percent > 0 && (
                                <div className="flex justify-between text-success">
                                    <dt>Your discount ({reseller.discount_percent}%)</dt>
                                    <dd className="tabular-nums">
                                        −<Money value={discount} />
                                    </dd>
                                </div>
                            )}
                            <div className="flex justify-between border-t border-cream-200 pt-2 font-display text-base font-semibold text-chocolate-700">
                                <dt>Order total</dt>
                                <dd className="tabular-nums">
                                    <Money value={total} />
                                </dd>
                            </div>
                            <div className="mt-2 flex justify-between rounded-xl bg-blush-50 px-3 py-2.5 font-semibold text-chocolate-700">
                                <dt>Pay now ({reseller.downpayment_percent}%)</dt>
                                <dd className="tabular-nums">
                                    <Money value={downpayment} />
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div className="card card-pad space-y-4">
                        <h2 className="font-display text-lg font-semibold text-chocolate-700">When do you need it?</h2>

                        <div className="grid grid-cols-2 gap-2">
                            {['pickup', 'delivery'].map((type) => (
                                <button
                                    key={type}
                                    type="button"
                                    onClick={() => setData('fulfillment_type', type)}
                                    className={clsx(
                                        'rounded-xl border px-4 py-2.5 text-sm font-semibold capitalize transition',
                                        data.fulfillment_type === type
                                            ? 'border-chocolate-700 bg-chocolate-700 text-cream-100'
                                            : 'border-cream-300 bg-vanilla text-chocolate-500 hover:bg-cream-100',
                                    )}
                                >
                                    {type}
                                </button>
                            ))}
                        </div>

                        {data.fulfillment_type === 'delivery' && (
                            <Field label="Delivery address" required error={errors.delivery_address}>
                                <Textarea
                                    rows={2}
                                    value={data.delivery_address}
                                    onChange={(e) => setData('delivery_address', e.target.value)}
                                    placeholder="Street, barangay, city"
                                />
                            </Field>
                        )}

                        <div className="grid grid-cols-2 gap-3">
                            <Field label="Date needed" required error={errors.date_needed}>
                                <Input
                                    type="date"
                                    value={data.date_needed}
                                    min={new Date().toISOString().slice(0, 10)}
                                    onChange={(e) => setData('date_needed', e.target.value)}
                                />
                            </Field>
                            <Field label="Time" error={errors.time_needed}>
                                <Input
                                    type="time"
                                    value={data.time_needed}
                                    onChange={(e) => setData('time_needed', e.target.value)}
                                />
                            </Field>
                        </div>

                        <Field label="Notes for the kitchen" error={errors.notes}>
                            <Textarea
                                rows={2}
                                value={data.notes}
                                onChange={(e) => setData('notes', e.target.value)}
                                placeholder="Anything we should know about packing or pickup?"
                            />
                        </Field>

                        <Button
                            type="submit"
                            disabled={processing || lines.length === 0}
                            className="w-full py-3 text-base"
                        >
                            {processing ? (
                                <>
                                    <Loader2 className="h-4 w-4 animate-spin" /> Placing order…
                                </>
                            ) : (
                                <>
                                    <ShoppingBag className="h-4 w-4" /> Place order
                                </>
                            )}
                        </Button>

                        <p className="text-center text-xs leading-relaxed text-chocolate-400">
                            Your order is reserved once the {reseller.downpayment_percent}% downpayment clears. The
                            balance stays visible until it is settled.
                        </p>
                    </div>
                </div>
            </form>
        </PortalLayout>
    );
}
