import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import clsx from 'clsx';
import {
    Banknote,
    History,
    LayoutDashboard,
    LogOut,
    Minus,
    Plus,
    QrCode,
    Search,
    ShoppingCart,
    Smartphone,
    Trash2,
} from 'lucide-react';
import { Button, FlashToasts, Input, Logo, Money, peso } from '@/Components/Lileu/ui';
import { ProductImage } from '@/Components/Lileu/product';

const METHODS = [
    ['cash', 'Cash', Banknote],
    ['gcash', 'GCash', Smartphone],
    ['qrph', 'QR Ph', QrCode],
];

const QUICK_CASH = [20, 50, 100, 200, 500, 1000];

export default function Terminal({ products, categories, todayTotal, todayCount }) {
    const { auth, brand, flash } = usePage().props;
    const [term, setTerm] = useState('');
    const [categoryId, setCategoryId] = useState(null);
    const [cart, setCart] = useState({});

    const form = useForm({
        items: [],
        discount: 0,
        method: 'cash',
        amount_tendered: '',
        customer_name: '',
    });

    // A completed sale flashes its number back; pop the thermal slip for the cashier.
    useEffect(() => {
        if (flash?.success && flash?.info) {
            window.open(route('pos.sales.receipt', flash.info), '_blank', 'width=420,height=720');
        }
    }, [flash?.success, flash?.info]);

    const visible = useMemo(
        () =>
            products.filter(
                (p) =>
                    (!categoryId || p.category_id === categoryId) &&
                    (!term ||
                        p.name.toLowerCase().includes(term.toLowerCase()) ||
                        p.sku.toLowerCase().includes(term.toLowerCase())),
            ),
        [products, term, categoryId],
    );

    const lines = useMemo(
        () =>
            Object.entries(cart)
                .filter(([, qty]) => qty > 0)
                .map(([id, qty]) => {
                    const product = products.find((p) => String(p.id) === id);

                    return { product, quantity: qty, lineTotal: (product?.price ?? 0) * qty };
                }),
        [cart, products],
    );

    const subtotal = lines.reduce((sum, l) => sum + l.lineTotal, 0);
    const discount = Number(form.data.discount) || 0;
    const total = Math.max(subtotal - discount, 0);
    const tendered = form.data.amount_tendered === '' ? total : Number(form.data.amount_tendered);
    const change = Math.max(tendered - total, 0);
    const shortfall = total - tendered;

    const setQty = (product, qty) =>
        setCart((prev) => {
            const next = { ...prev };
            const clamped = Math.max(0, qty);

            if (clamped === 0) delete next[product.id];
            else next[product.id] = clamped;

            return next;
        });

    const clearCart = () => {
        setCart({});
        form.setData({ items: [], discount: 0, method: 'cash', amount_tendered: '', customer_name: '' });
    };

    const checkout = () => {
        form.transform((data) => ({
            ...data,
            amount_tendered: data.amount_tendered === '' ? total : data.amount_tendered,
            items: lines.map((l) => ({ product_id: l.product.id, quantity: l.quantity })),
        }));

        form.post(route('pos.sales.store'), { onSuccess: () => clearCart() });
    };

    return (
        <div className="flex h-screen flex-col overflow-hidden bg-cream-100">
            <Head title="Point of sale" />
            <FlashToasts />

            {/* Top bar */}
            <header className="flex shrink-0 items-center justify-between gap-4 bg-chocolate-700 px-4 py-3 text-cream-100">
                <div className="flex items-center gap-2.5">
                    <Logo className="h-9 w-9" />
                    <span className="leading-tight">
                        <span className="block font-display text-base font-semibold">{brand?.name}</span>
                        <span className="block text-[11px] uppercase tracking-[0.16em] text-blush-300">
                            Point of sale
                        </span>
                    </span>
                </div>

                <div className="hidden items-center gap-6 sm:flex">
                    <div className="text-right leading-tight">
                        <span className="block text-[11px] uppercase tracking-wider text-cream-200/50">
                            Today
                        </span>
                        <span className="block font-display text-lg font-semibold">
                            {peso(todayTotal)} · {todayCount} sales
                        </span>
                    </div>
                </div>

                <div className="flex items-center gap-1.5">
                    <button
                        type="button"
                        onClick={() => router.visit(route('pos.sales.index'))}
                        className="rounded-xl p-2.5 text-cream-200/70 transition hover:bg-cream-100/10 hover:text-cream-100"
                        aria-label="Sales history"
                    >
                        <History className="h-5 w-5" />
                    </button>
                    {auth?.user?.role === 'admin' && (
                        <button
                            type="button"
                            onClick={() => router.visit(route('admin.dashboard'))}
                            className="rounded-xl p-2.5 text-cream-200/70 transition hover:bg-cream-100/10 hover:text-cream-100"
                            aria-label="Admin dashboard"
                        >
                            <LayoutDashboard className="h-5 w-5" />
                        </button>
                    )}
                    <button
                        type="button"
                        onClick={() => router.post(route('logout'))}
                        className="rounded-xl p-2.5 text-cream-200/70 transition hover:bg-cream-100/10 hover:text-blush-300"
                        aria-label="Log out"
                    >
                        <LogOut className="h-5 w-5" />
                    </button>
                </div>
            </header>

            <div className="flex min-h-0 flex-1 flex-col lg:flex-row">
                {/* Products */}
                <div className="flex min-h-0 flex-1 flex-col p-3 sm:p-4">
                    <div className="mb-3 flex flex-wrap items-center gap-2">
                        <div className="relative min-w-48 flex-1">
                            <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-chocolate-300" />
                            <Input
                                value={term}
                                onChange={(e) => setTerm(e.target.value)}
                                placeholder="Search product or SKU…"
                                className="pl-10"
                            />
                        </div>
                    </div>

                    <div className="mb-3 flex flex-wrap gap-1.5">
                        <button
                            type="button"
                            onClick={() => setCategoryId(null)}
                            className={clsx(
                                'rounded-xl px-3.5 py-2 text-sm font-semibold transition',
                                categoryId === null
                                    ? 'bg-chocolate-700 text-cream-100'
                                    : 'bg-vanilla text-chocolate-500 hover:bg-cream-200',
                            )}
                        >
                            All
                        </button>
                        {categories.map((category) => (
                            <button
                                key={category.id}
                                type="button"
                                onClick={() => setCategoryId(category.id)}
                                className={clsx(
                                    'rounded-xl px-3.5 py-2 text-sm font-semibold transition',
                                    categoryId === category.id
                                        ? 'bg-chocolate-700 text-cream-100'
                                        : 'bg-vanilla text-chocolate-500 hover:bg-cream-200',
                                )}
                            >
                                {category.name}
                            </button>
                        ))}
                    </div>

                    <div className="grid min-h-0 flex-1 auto-rows-max grid-cols-2 gap-2.5 overflow-y-auto pb-2 sm:grid-cols-3 xl:grid-cols-4">
                        {visible.map((product) => {
                            const qty = cart[product.id] ?? 0;

                            return (
                                <button
                                    key={product.id}
                                    type="button"
                                    onClick={() => setQty(product, qty + 1)}
                                    className={clsx(
                                        'relative flex flex-col overflow-hidden rounded-2xl border bg-vanilla text-left shadow-soft transition active:scale-[.98]',
                                        qty > 0 ? 'border-blush-400 ring-2 ring-blush-200' : 'border-cream-300/70',
                                    )}
                                >
                                    <div className="aspect-[4/3] w-full bg-cream-200">
                                        <ProductImage product={product} accent={product.accent} />
                                    </div>

                                    {qty > 0 && (
                                        <span className="absolute right-2 top-2 flex h-7 min-w-7 items-center justify-center rounded-full bg-chocolate-700 px-1.5 text-sm font-bold text-cream-100">
                                            {qty}
                                        </span>
                                    )}

                                    <div className="p-3">
                                        <p className="line-clamp-2 text-sm font-semibold leading-tight text-chocolate-700">
                                            {product.name}
                                        </p>
                                        <p className="mt-1 font-display text-base font-semibold text-chocolate-700">
                                            {peso(product.price, { decimals: 0 })}
                                        </p>
                                        <p
                                            className={clsx(
                                                'mt-0.5 text-[11px]',
                                                product.stock > 0 ? 'text-chocolate-300' : 'text-cherry',
                                            )}
                                        >
                                            {product.stock > 0 ? `${product.stock} in stock` : 'Out of stock'}
                                        </p>
                                    </div>
                                </button>
                            );
                        })}
                    </div>
                </div>

                {/* Cart */}
                <aside className="flex w-full shrink-0 flex-col border-t border-cream-300 bg-vanilla lg:w-96 lg:border-l lg:border-t-0">
                    <div className="flex items-center justify-between border-b border-cream-200 px-4 py-3">
                        <span className="flex items-center gap-2 font-display text-base font-semibold text-chocolate-700">
                            <ShoppingCart className="h-5 w-5 text-blush-500" />
                            Cart
                            {lines.length > 0 && (
                                <span className="rounded-full bg-blush-100 px-2 py-0.5 text-xs text-blush-600">
                                    {lines.reduce((s, l) => s + l.quantity, 0)}
                                </span>
                            )}
                        </span>
                        {lines.length > 0 && (
                            <button
                                type="button"
                                onClick={clearCart}
                                className="text-xs font-semibold text-chocolate-400 transition hover:text-cherry"
                            >
                                Clear
                            </button>
                        )}
                    </div>

                    <div className="min-h-24 flex-1 overflow-y-auto px-3 py-2">
                        {lines.length === 0 ? (
                            <p className="mt-8 px-4 text-center text-sm text-chocolate-300">
                                Tap a product to start a sale.
                            </p>
                        ) : (
                            <ul className="divide-y divide-cream-200">
                                {lines.map((line) => (
                                    <li key={line.product.id} className="flex items-center gap-2 py-2.5">
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium text-chocolate-700">
                                                {line.product.name}
                                            </p>
                                            <p className="text-xs text-chocolate-400">
                                                {peso(line.product.price)} each
                                            </p>
                                        </div>

                                        <div className="flex items-center gap-1">
                                            <button
                                                type="button"
                                                onClick={() => setQty(line.product, line.quantity - 1)}
                                                className="flex h-8 w-8 items-center justify-center rounded-lg border border-cream-300 text-chocolate-600 transition hover:bg-cream-100"
                                                aria-label="Decrease"
                                            >
                                                <Minus className="h-4 w-4" />
                                            </button>
                                            <span className="w-8 text-center text-sm font-semibold tabular-nums">
                                                {line.quantity}
                                            </span>
                                            <button
                                                type="button"
                                                onClick={() => setQty(line.product, line.quantity + 1)}
                                                className="flex h-8 w-8 items-center justify-center rounded-lg border border-cream-300 text-chocolate-600 transition hover:bg-cream-100"
                                                aria-label="Increase"
                                            >
                                                <Plus className="h-4 w-4" />
                                            </button>
                                        </div>

                                        <span className="w-16 text-right text-sm font-semibold tabular-nums text-chocolate-700">
                                            {peso(line.lineTotal)}
                                        </span>

                                        <button
                                            type="button"
                                            onClick={() => setQty(line.product, 0)}
                                            className="rounded-lg p-1 text-chocolate-300 transition hover:bg-cherry/10 hover:text-cherry"
                                            aria-label="Remove"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    {/* Checkout */}
                    <div className="shrink-0 border-t border-cream-200 bg-cream-50 p-3.5">
                        <div className="mb-3 grid grid-cols-3 gap-1.5">
                            {METHODS.map(([value, label, Icon]) => (
                                <button
                                    key={value}
                                    type="button"
                                    onClick={() => form.setData('method', value)}
                                    className={clsx(
                                        'flex flex-col items-center gap-1 rounded-xl border px-2 py-2.5 text-xs font-semibold transition',
                                        form.data.method === value
                                            ? 'border-chocolate-700 bg-chocolate-700 text-cream-100'
                                            : 'border-cream-300 bg-vanilla text-chocolate-500',
                                    )}
                                >
                                    <Icon className="h-4 w-4" />
                                    {label}
                                </button>
                            ))}
                        </div>

                        <dl className="space-y-1 text-sm">
                            <div className="flex justify-between text-chocolate-500">
                                <dt>Subtotal</dt>
                                <dd className="tabular-nums">{peso(subtotal)}</dd>
                            </div>
                            <div className="flex items-center justify-between text-chocolate-500">
                                <dt>Discount</dt>
                                <dd>
                                    <Input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={form.data.discount}
                                        onChange={(e) => form.setData('discount', e.target.value)}
                                        className="h-8 w-24 py-1 text-right text-sm"
                                    />
                                </dd>
                            </div>
                        </dl>

                        <div className="my-3 flex items-center justify-between rounded-xl bg-chocolate-800 px-4 py-3 text-cream-100">
                            <span className="text-[11px] font-bold uppercase tracking-[0.16em] text-blush-300">
                                Total
                            </span>
                            <span className="font-display text-2xl font-semibold tabular-nums">{peso(total)}</span>
                        </div>

                        {form.data.method === 'cash' && (
                            <>
                                <div className="mb-2 grid grid-cols-3 gap-1.5">
                                    {QUICK_CASH.map((amount) => (
                                        <button
                                            key={amount}
                                            type="button"
                                            onClick={() => form.setData('amount_tendered', amount)}
                                            className="rounded-lg border border-cream-300 bg-vanilla py-1.5 text-xs font-semibold text-chocolate-600 transition hover:bg-cream-200"
                                        >
                                            {peso(amount, { decimals: 0 })}
                                        </button>
                                    ))}
                                </div>

                                <div className="mb-2 flex items-center gap-2">
                                    <Input
                                        type="number"
                                        step="0.01"
                                        value={form.data.amount_tendered}
                                        onChange={(e) => form.setData('amount_tendered', e.target.value)}
                                        placeholder="Cash received"
                                        className="text-right"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => form.setData('amount_tendered', total)}
                                        className="shrink-0 rounded-lg border border-cream-300 bg-vanilla px-3 py-2 text-xs font-semibold text-chocolate-600"
                                    >
                                        Exact
                                    </button>
                                </div>

                                <div
                                    className={clsx(
                                        'mb-3 flex justify-between rounded-xl px-3 py-2 text-sm font-semibold',
                                        shortfall > 0
                                            ? 'bg-cherry/10 text-cherry-dark'
                                            : 'bg-success-light text-success',
                                    )}
                                >
                                    <span>{shortfall > 0 ? 'Short by' : 'Change'}</span>
                                    <span className="tabular-nums">
                                        {peso(shortfall > 0 ? shortfall : change)}
                                    </span>
                                </div>
                            </>
                        )}

                        <Button
                            onClick={checkout}
                            disabled={form.processing || lines.length === 0 || (form.data.method === 'cash' && shortfall > 0)}
                            variant="success"
                            className="w-full py-3.5 text-base"
                        >
                            Complete sale · <Money value={total} decimals={0} />
                        </Button>
                    </div>
                </aside>
            </div>
        </div>
    );
}
