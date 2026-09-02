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
    TriangleAlert,
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

    // The terminal is a fixed tablet workspace. Only its product and cart panes scroll.
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
    const itemCount = lines.reduce((sum, line) => sum + line.quantity, 0);
    const checkoutError = form.errors.items || form.errors.discount || form.errors.amount_tendered;

    const setQty = (product, qty) => {
        form.clearErrors('items');

        setCart((prev) => {
            const next = { ...prev };
            const clamped = Math.min(product.stock, Math.max(0, qty));

            if (clamped === 0) delete next[product.id];
            else next[product.id] = clamped;

            return next;
        });
    };

    const clearCart = () => {
        setCart({});
        form.clearErrors();
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
        <div className="flex h-screen max-h-screen h-[100dvh] max-h-[100dvh] flex-col overflow-hidden overscroll-none bg-cream-100">
            <Head title="Point of sale" />
            <FlashToasts />

            {/* Top bar */}
            <header className="flex shrink-0 items-center justify-between gap-3 bg-chocolate-700 px-3 py-2.5 text-cream-100 sm:px-4">
                <div className="flex items-center gap-2.5">
                    <Logo className="h-8 w-8 sm:h-9 sm:w-9" />
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

            <main className="grid min-h-0 flex-1 grid-cols-1 grid-rows-[minmax(0,1fr)_minmax(20rem,55%)] overflow-hidden md:grid-cols-[minmax(0,1fr)_22rem] md:grid-rows-1 xl:grid-cols-[minmax(0,1fr)_25rem]">
                {/* Products */}
                <section className="flex min-h-0 min-w-0 flex-col p-2.5 sm:p-3">
                    <div className="mb-2.5 flex shrink-0 items-center gap-2">
                        <div className="relative min-w-48 flex-1">
                            <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-chocolate-300" />
                            <Input
                                value={term}
                                onChange={(e) => setTerm(e.target.value)}
                                placeholder="Search product or SKU…"
                                className="h-11 pl-10 text-base"
                            />
                        </div>
                        <span className="hidden shrink-0 rounded-xl bg-vanilla px-3 py-2 text-xs font-semibold text-chocolate-400 lg:inline-flex">
                            {visible.length} products
                        </span>
                    </div>

                    <div className="mb-2.5 flex shrink-0 gap-1.5 overflow-x-auto overscroll-x-contain pb-1">
                        <button
                            type="button"
                            onClick={() => setCategoryId(null)}
                            className={clsx(
                                'shrink-0 rounded-xl px-3.5 py-2 text-sm font-semibold transition',
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
                                    'shrink-0 rounded-xl px-3.5 py-2 text-sm font-semibold transition',
                                    categoryId === category.id
                                        ? 'bg-chocolate-700 text-cream-100'
                                        : 'bg-vanilla text-chocolate-500 hover:bg-cream-200',
                                )}
                            >
                                {category.name}
                            </button>
                        ))}
                    </div>

                    <div className="grid min-h-0 flex-1 auto-rows-max grid-cols-2 gap-2 overflow-y-auto overscroll-contain pr-1 pb-2 sm:grid-cols-3 md:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                        {visible.map((product) => {
                            const qty = cart[product.id] ?? 0;

                            return (
                                <button
                                    key={product.id}
                                    type="button"
                                    onClick={() => setQty(product, qty + 1)}
                                    disabled={product.stock === 0}
                                    aria-label={`Add ${product.name} to cart`}
                                    className={clsx(
                                        'relative flex flex-col overflow-hidden rounded-2xl border bg-vanilla text-left shadow-soft transition active:scale-[.98] disabled:cursor-not-allowed disabled:opacity-55',
                                        qty > 0 ? 'border-blush-400 ring-2 ring-blush-200' : 'border-cream-300/70',
                                    )}
                                >
                                    <div className="aspect-[16/9] w-full bg-cream-200 sm:aspect-[4/3] md:aspect-[16/9]">
                                        <ProductImage product={product} accent={product.accent} />
                                    </div>

                                    {qty > 0 && (
                                        <span className="absolute right-2 top-2 flex h-7 min-w-7 items-center justify-center rounded-full bg-chocolate-700 px-1.5 text-sm font-bold text-cream-100">
                                            {qty}
                                        </span>
                                    )}

                                    <div className="p-2.5">
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
                        {visible.length === 0 && (
                            <div className="col-span-full flex min-h-40 flex-col items-center justify-center rounded-2xl border border-dashed border-cream-300 bg-vanilla/50 px-4 text-center text-sm text-chocolate-300">
                                <Search className="mb-2 h-7 w-7 text-chocolate-200" />
                                No products match this search or category.
                            </div>
                        )}
                    </div>
                </section>

                {/* Cart */}
                <aside className="flex min-h-0 min-w-0 flex-col overflow-hidden border-t border-cream-300 bg-vanilla md:border-l md:border-t-0">
                    <div className="flex shrink-0 items-center justify-between border-b border-cream-200 px-3.5 py-2.5">
                        <span className="flex items-center gap-2 font-display text-base font-semibold text-chocolate-700">
                            <ShoppingCart className="h-5 w-5 text-blush-500" />
                            Cart
                            {lines.length > 0 && (
                                <span className="rounded-full bg-blush-100 px-2 py-0.5 text-xs text-blush-600">
                                    {itemCount}
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

                    <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 py-1.5">
                        {lines.length === 0 ? (
                            <div className="flex h-full min-h-20 flex-col items-center justify-center px-4 text-center text-sm text-chocolate-300">
                                <ShoppingCart className="mb-2 h-7 w-7 text-chocolate-200" />
                                Tap a product to start a sale.
                            </div>
                        ) : (
                            <ul className="divide-y divide-cream-200">
                                {lines.map((line) => (
                                    <li key={line.product.id} className="flex min-h-14 items-center gap-2 py-2">
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
                                                className="flex h-9 w-9 items-center justify-center rounded-lg border border-cream-300 text-chocolate-600 transition hover:bg-cream-100"
                                                aria-label={`Decrease ${line.product.name}`}
                                            >
                                                <Minus className="h-4 w-4" />
                                            </button>
                                            <span className="w-8 text-center text-sm font-semibold tabular-nums">
                                                {line.quantity}
                                            </span>
                                            <button
                                                type="button"
                                                onClick={() => setQty(line.product, line.quantity + 1)}
                                                disabled={line.quantity >= line.product.stock}
                                                className="flex h-9 w-9 items-center justify-center rounded-lg border border-cream-300 text-chocolate-600 transition hover:bg-cream-100 disabled:opacity-40"
                                                aria-label={`Increase ${line.product.name}`}
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
                                            aria-label={`Remove ${line.product.name}`}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    {/* Checkout */}
                    <div className="shrink-0 border-t border-cream-200 bg-cream-50 p-3">
                        <div className="mb-2 grid grid-cols-3 gap-1.5">
                            {METHODS.map(([value, label, Icon]) => (
                                <button
                                    key={value}
                                    type="button"
                                    onClick={() => {
                                        form.clearErrors('amount_tendered');
                                        form.setData('method', value);
                                    }}
                                    className={clsx(
                                        'flex items-center justify-center gap-1.5 rounded-xl border px-2 py-2 text-xs font-semibold transition',
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
                                        onChange={(e) => {
                                            form.clearErrors('discount');
                                            form.setData('discount', e.target.value);
                                        }}
                                        className="h-8 w-24 py-1 text-right text-sm"
                                    />
                                </dd>
                            </div>
                        </dl>

                        <div className="my-2 flex items-center justify-between rounded-xl bg-chocolate-800 px-4 py-2.5 text-cream-100">
                            <span className="text-[11px] font-bold uppercase tracking-[0.16em] text-blush-300">
                                Total
                            </span>
                            <span className="font-display text-xl font-semibold tabular-nums sm:text-2xl">{peso(total)}</span>
                        </div>

                        {form.data.method === 'cash' && (
                            <>
                                <div className="mb-2 grid grid-cols-6 gap-1">
                                    {QUICK_CASH.map((amount) => (
                                        <button
                                            key={amount}
                                            type="button"
                                            onClick={() => {
                                                form.clearErrors('amount_tendered');
                                                form.setData('amount_tendered', amount);
                                            }}
                                            className="rounded-lg border border-cream-300 bg-vanilla px-1 py-1.5 text-[11px] font-semibold text-chocolate-600 transition hover:bg-cream-200"
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
                                        onChange={(e) => {
                                            form.clearErrors('amount_tendered');
                                            form.setData('amount_tendered', e.target.value);
                                        }}
                                        placeholder="Cash received"
                                        className="text-right"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => {
                                            form.clearErrors('amount_tendered');
                                            form.setData('amount_tendered', total);
                                        }}
                                        className="shrink-0 rounded-lg border border-cream-300 bg-vanilla px-3 py-2 text-xs font-semibold text-chocolate-600"
                                    >
                                        Exact
                                    </button>
                                </div>

                                <div
                                    className={clsx(
                                        'mb-2 flex justify-between rounded-xl px-3 py-1.5 text-sm font-semibold',
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

                        {checkoutError && (
                            <p className="mb-2 flex items-start gap-1.5 rounded-lg bg-cherry/10 px-3 py-2 text-xs font-medium text-cherry-dark">
                                <TriangleAlert className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                {checkoutError}
                            </p>
                        )}

                        <Button
                            onClick={checkout}
                            disabled={
                                form.processing ||
                                lines.length === 0 ||
                                (form.data.method === 'cash' && shortfall > 0)
                            }
                            variant="success"
                            className="w-full py-3 text-base"
                        >
                            Complete sale · <Money value={total} decimals={0} />
                        </Button>
                    </div>
                </aside>
            </main>
        </div>
    );
}
