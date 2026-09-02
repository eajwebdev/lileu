import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import clsx from 'clsx';
import {
    BookOpen,
    ChartNoAxesColumn,
    CreditCard,
    HandCoins,
    LayoutDashboard,
    LogOut,
    Menu,
    MessageCircle,
    Package,
    Receipt,
    Settings,
    ShoppingCart,
    Users,
    UsersRound,
} from 'lucide-react';
import { FlashToasts, Logo } from '@/Components/Lileu/ui';

const GROUPS = [
    {
        label: 'Overview',
        items: [{ label: 'Dashboard', icon: LayoutDashboard, route: 'admin.dashboard', match: 'admin.dashboard' }],
    },
    {
        label: 'Selling',
        items: [
            { label: 'Reseller Orders', icon: Receipt, route: 'admin.orders.index', match: 'admin.orders.*' },
            { label: 'Consignments', icon: HandCoins, route: 'admin.consignments.index', match: 'admin.consignments.*' },
            { label: 'Payments', icon: CreditCard, route: 'admin.payments.index', match: 'admin.payments.index' },
            { label: 'Resellers', icon: UsersRound, route: 'admin.resellers.index', match: 'admin.resellers.*' },
            { label: 'Messages', icon: MessageCircle, route: 'admin.messages.index', match: 'admin.messages.*' },
        ],
    },
    {
        label: 'Operations',
        items: [
            { label: 'Products', icon: Package, route: 'admin.products.index', match: 'admin.products.*' },
            { label: 'Point of Sale', icon: ShoppingCart, route: 'pos.index', match: 'pos.index' },
            { label: 'Expenses & Purchases', icon: BookOpen, route: 'admin.ledger.index', match: 'admin.ledger.*' },
            { label: 'Reports', icon: ChartNoAxesColumn, route: 'admin.reports.index', match: 'admin.reports.*' },
        ],
    },
    {
        label: 'Settings',
        items: [
            { label: 'Users', icon: Users, route: 'admin.users.index', match: 'admin.users.*' },
            { label: 'Brand & Receipts', icon: Settings, route: 'admin.settings.edit', match: 'admin.settings.*' },
        ],
    },
];

function SidebarContent({ onNavigate }) {
    const { brand, auth } = usePage().props;

    return (
        <div className="flex h-full flex-col bg-choco-fade text-cream-200">
            <div className="flex items-center gap-3 px-5 py-5">
                <Logo className="h-10 w-10" />
                <span className="leading-tight">
                    <span className="block font-display text-base font-semibold text-cream-100">{brand?.name}</span>
                    <span className="block text-[11px] uppercase tracking-[0.16em] text-blush-300">
                        Management
                    </span>
                </span>
            </div>

            <nav className="flex-1 space-y-5 overflow-y-auto px-3 pb-6">
                {GROUPS.map((group) => (
                    <div key={group.label}>
                        <p className="px-3 pb-1.5 text-[10px] font-semibold uppercase tracking-[0.18em] text-cream-200/40">
                            {group.label}
                        </p>
                        <div className="space-y-0.5">
                            {group.items.map((item) => {
                                const active = route().current(item.match);

                                return (
                                    <Link
                                        key={item.route}
                                        href={route(item.route)}
                                        onClick={onNavigate}
                                        className={clsx(
                                            'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                                            active
                                                ? 'bg-cream-100/95 text-chocolate-700 shadow-soft'
                                                : 'text-cream-200/70 hover:bg-cream-100/10 hover:text-cream-100',
                                        )}
                                    >
                                        <item.icon
                                            className={clsx('h-[1.125rem] w-[1.125rem] shrink-0', active ? 'text-blush-500' : '')}
                                        />
                                        <span className="truncate">{item.label}</span>
                                    </Link>
                                );
                            })}
                        </div>
                    </div>
                ))}
            </nav>

            <div className="border-t border-cream-200/10 px-3 py-3">
                <div className="flex items-center gap-3 rounded-xl px-3 py-2">
                    <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-blush-300 font-display text-sm font-bold text-chocolate-800">
                        {auth?.user?.name?.[0] ?? 'L'}
                    </span>
                    <span className="min-w-0 flex-1 leading-tight">
                        <span className="block truncate text-sm font-medium text-cream-100">{auth?.user?.name}</span>
                        <span className="block truncate text-[11px] capitalize text-cream-200/50">
                            {auth?.user?.role}
                        </span>
                    </span>
                    <button
                        type="button"
                        onClick={() => router.post(route('logout'))}
                        className="rounded-lg p-2 text-cream-200/60 transition hover:bg-cream-100/10 hover:text-blush-300"
                        aria-label="Log out"
                    >
                        <LogOut className="h-4 w-4" />
                    </button>
                </div>
            </div>
        </div>
    );
}

export default function AdminLayout({ title, subtitle, action, children }) {
    const [open, setOpen] = useState(false);

    return (
        <div className="min-h-screen bg-cream-100">
            <Head title={title} />
            <FlashToasts />

            <div className="flex">
                <aside className="no-print sticky top-0 hidden h-screen w-64 shrink-0 lg:block">
                    <SidebarContent />
                </aside>

                {open && (
                    <div className="no-print fixed inset-0 z-50 lg:hidden">
                        <div
                            className="absolute inset-0 bg-chocolate-900/50 backdrop-blur-sm"
                            onClick={() => setOpen(false)}
                        />
                        <aside className="absolute inset-y-0 left-0 w-72 animate-fade-up">
                            <SidebarContent onNavigate={() => setOpen(false)} />
                        </aside>
                    </div>
                )}

                <div className="min-w-0 flex-1">
                    <header className="no-print sticky top-0 z-30 flex items-center gap-3 border-b border-cream-300/60 bg-cream-100/90 px-4 py-3 backdrop-blur-md lg:hidden">
                        <button
                            type="button"
                            onClick={() => setOpen(true)}
                            className="rounded-xl p-2 text-chocolate-600 transition hover:bg-cream-200"
                            aria-label="Open menu"
                        >
                            <Menu className="h-6 w-6" />
                        </button>
                        <span className="font-display text-lg font-semibold text-chocolate-700">{title}</span>
                    </header>

                    <main className="px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                        <div className="mb-6 hidden flex-wrap items-end justify-between gap-3 lg:flex">
                            <div>
                                <h1 className="font-display text-2xl font-semibold tracking-tight text-chocolate-700 sm:text-3xl">
                                    {title}
                                </h1>
                                {subtitle && <p className="mt-1 text-sm text-chocolate-400">{subtitle}</p>}
                            </div>
                            {action}
                        </div>

                        {action && <div className="mb-5 lg:hidden">{action}</div>}

                        {children}
                    </main>
                </div>
            </div>
        </div>
    );
}
