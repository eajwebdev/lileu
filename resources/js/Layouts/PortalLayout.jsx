import { Link, router, usePage } from '@inertiajs/react';
import clsx from 'clsx';
import { Head } from '@inertiajs/react';
import { LayoutGrid, LogOut, MessageCircle, Package, Receipt, ShoppingBag, UserRound } from 'lucide-react';
import { FlashToasts, Logo } from '@/Components/Lileu/ui';

const NAV = [
    { label: 'Home', icon: LayoutGrid, route: 'portal.dashboard', match: 'portal.dashboard' },
    { label: 'Catalog', icon: Package, route: 'portal.catalog', match: 'portal.catalog' },
    { label: 'Order', icon: ShoppingBag, route: 'portal.orders.create', match: 'portal.orders.create' },
    { label: 'Orders', icon: Receipt, route: 'portal.orders.index', match: 'portal.orders.*' },
    { label: 'Chat', icon: MessageCircle, route: 'portal.messages.index', match: 'portal.messages.*' },
];

export default function PortalLayout({ title, subtitle, action, children }) {
    const { auth, brand } = usePage().props;

    const isActive = (item) =>
        item.match === 'portal.orders.*'
            ? route().current('portal.orders.index') || route().current('portal.orders.show')
            : route().current(item.match);

    return (
        <div className="min-h-screen bg-cream-100 pb-24 md:pb-0">
            <Head title={title} />
            <FlashToasts />

            {/* Desktop chrome */}
            <header className="sticky top-0 z-40 border-b border-cream-300/60 bg-cream-100/90 backdrop-blur-md">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
                    <Link href={route('portal.dashboard')} className="flex items-center gap-2.5">
                        <Logo className="h-9 w-9" />
                        <span className="leading-tight">
                            <span className="block font-display text-base font-semibold text-chocolate-700">
                                {brand?.name}
                            </span>
                            <span className="block text-[11px] font-medium uppercase tracking-[0.16em] text-blush-500">
                                Reseller Portal
                            </span>
                        </span>
                    </Link>

                    <nav className="hidden items-center gap-1 md:flex">
                        {NAV.map((item) => (
                            <Link
                                key={item.route}
                                href={route(item.route)}
                                className={clsx(
                                    'flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-medium transition',
                                    isActive(item)
                                        ? 'bg-chocolate-700 text-cream-100'
                                        : 'text-chocolate-500 hover:bg-cream-200 hover:text-chocolate-700',
                                )}
                            >
                                <item.icon className="h-4 w-4" />
                                {item.label}
                            </Link>
                        ))}
                    </nav>

                    <div className="flex items-center gap-2">
                        <Link
                            href={route('profile.edit')}
                            className="hidden items-center gap-2 rounded-xl border border-cream-300 bg-vanilla px-3 py-2 text-sm font-medium text-chocolate-600 transition hover:bg-cream-100 sm:flex"
                        >
                            <UserRound className="h-4 w-4 text-blush-500" />
                            <span className="max-w-28 truncate">{auth?.user?.name}</span>
                        </Link>
                        <button
                            type="button"
                            onClick={() => router.post(route('logout'))}
                            className="rounded-xl p-2.5 text-chocolate-500 transition hover:bg-cream-200 hover:text-cherry"
                            aria-label="Log out"
                        >
                            <LogOut className="h-5 w-5" />
                        </button>
                    </div>
                </div>
            </header>

            <main className="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-8">
                {(title || action) && (
                    <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h1 className="font-display text-2xl font-semibold tracking-tight text-chocolate-700 sm:text-3xl">
                                {title}
                            </h1>
                            {subtitle && <p className="mt-1 text-sm text-chocolate-400">{subtitle}</p>}
                        </div>
                        {action}
                    </div>
                )}

                {children}
            </main>

            {/* Mobile tab bar — resellers live on their phones. */}
            <nav className="no-print fixed inset-x-0 bottom-0 z-40 border-t border-cream-300/70 bg-vanilla/95 backdrop-blur-md md:hidden">
                <div className="mx-auto flex max-w-lg items-stretch justify-between px-2 py-1.5">
                    {NAV.map((item) => {
                        const active = isActive(item);

                        return (
                            <Link
                                key={item.route}
                                href={route(item.route)}
                                className={clsx(
                                    'flex flex-1 flex-col items-center gap-1 rounded-xl px-1 py-2 text-[11px] font-medium transition',
                                    active ? 'text-chocolate-700' : 'text-chocolate-400',
                                )}
                            >
                                <span
                                    className={clsx(
                                        'flex h-8 w-12 items-center justify-center rounded-lg transition',
                                        active && 'bg-blush-100',
                                    )}
                                >
                                    <item.icon className="h-5 w-5" />
                                </span>
                                {item.label}
                            </Link>
                        );
                    })}
                </div>
            </nav>
        </div>
    );
}
