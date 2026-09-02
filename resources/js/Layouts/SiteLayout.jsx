import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import clsx from 'clsx';
import { Facebook, Mail, MapPin, Menu, Phone, X } from 'lucide-react';
import { ButtonLink, FlashToasts, Logo } from '@/Components/Lileu/ui';

const NAV = [
    { label: 'Home', route: 'home' },
    { label: 'Desserts', route: 'products.index' },
    { label: 'Become a Reseller', route: 'reseller.apply' },
];

export default function SiteLayout({ children }) {
    const { brand, auth } = usePage().props;
    const [open, setOpen] = useState(false);

    return (
        <div className="min-h-screen bg-cream-100">
            <FlashToasts />

            <header className="sticky top-0 z-40 border-b border-cream-300/60 bg-cream-100/85 backdrop-blur-md">
                <div className="mx-auto flex h-[4.5rem] max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
                    <Link href={route('home')} className="flex items-center gap-2.5">
                        <Logo className="h-10 w-10" />
                        <span className="leading-tight">
                            <span className="block font-display text-lg font-semibold tracking-tight text-chocolate-700">
                                {brand?.name}
                            </span>
                            <span className="block text-[11px] font-medium uppercase tracking-[0.16em] text-blush-500">
                                {brand?.tagline}
                            </span>
                        </span>
                    </Link>

                    <nav className="hidden items-center gap-1 md:flex">
                        {NAV.map((item) => (
                            <Link
                                key={item.route}
                                href={route(item.route)}
                                className={clsx(
                                    'rounded-xl px-3.5 py-2 text-sm font-medium transition',
                                    route().current(item.route)
                                        ? 'bg-blush-100 text-chocolate-700'
                                        : 'text-chocolate-500 hover:bg-cream-200 hover:text-chocolate-700',
                                )}
                            >
                                {item.label}
                            </Link>
                        ))}
                    </nav>

                    <div className="hidden items-center gap-2 md:flex">
                        {auth?.user ? (
                            <ButtonLink href={route('dashboard')} variant="primary">
                                My Portal
                            </ButtonLink>
                        ) : (
                            <>
                                <ButtonLink href={route('login')} variant="ghost">
                                    Log in
                                </ButtonLink>
                                <ButtonLink href={route('reseller.apply')} variant="primary">
                                    Apply
                                </ButtonLink>
                            </>
                        )}
                    </div>

                    <button
                        type="button"
                        onClick={() => setOpen((v) => !v)}
                        className="rounded-xl p-2 text-chocolate-600 transition hover:bg-cream-200 md:hidden"
                        aria-label="Toggle menu"
                    >
                        {open ? <X className="h-6 w-6" /> : <Menu className="h-6 w-6" />}
                    </button>
                </div>

                {open && (
                    <div className="border-t border-cream-300/60 bg-cream-100 px-4 py-3 md:hidden">
                        <nav className="flex flex-col gap-1">
                            {NAV.map((item) => (
                                <Link
                                    key={item.route}
                                    href={route(item.route)}
                                    onClick={() => setOpen(false)}
                                    className="rounded-xl px-3 py-2.5 text-sm font-medium text-chocolate-600 hover:bg-cream-200"
                                >
                                    {item.label}
                                </Link>
                            ))}
                            <div className="mt-2 grid grid-cols-2 gap-2">
                                {auth?.user ? (
                                    <ButtonLink href={route('dashboard')} className="col-span-2">
                                        My Portal
                                    </ButtonLink>
                                ) : (
                                    <>
                                        <ButtonLink href={route('login')} variant="ghost">
                                            Log in
                                        </ButtonLink>
                                        <ButtonLink href={route('reseller.apply')}>Apply</ButtonLink>
                                    </>
                                )}
                            </div>
                        </nav>
                    </div>
                )}
            </header>

            <main>{children}</main>

            <footer className="mt-24 bg-choco-fade text-cream-200">
                <div className="mx-auto grid max-w-6xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-[1.4fr_1fr_1fr]">
                    <div>
                        <div className="flex items-center gap-3">
                            <span className="inline-flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-full bg-cream-100">
                                <img src={brand?.logo_mark} alt="" className="h-full w-full object-contain" />
                            </span>
                            <span>
                                <span className="block font-display text-xl font-semibold text-cream-100">
                                    {brand?.name}
                                </span>
                                <span className="block text-xs uppercase tracking-[0.18em] text-blush-300">
                                    {brand?.tagline}
                                </span>
                            </span>
                        </div>
                        <p className="mt-4 max-w-sm text-sm leading-relaxed text-cream-200/70">
                            Small-batch desserts made daily, and a reseller programme built for people who want to
                            grow a sweet little business of their own.
                        </p>
                    </div>

                    <div>
                        <p className="mb-3 text-xs font-semibold uppercase tracking-[0.18em] text-blush-300">Explore</p>
                        <ul className="space-y-2 text-sm text-cream-200/75">
                            <li>
                                <Link href={route('products.index')} className="transition hover:text-cream-100">
                                    All desserts
                                </Link>
                            </li>
                            <li>
                                <Link href={route('reseller.apply')} className="transition hover:text-cream-100">
                                    Become a reseller
                                </Link>
                            </li>
                            <li>
                                <Link href={route('login')} className="transition hover:text-cream-100">
                                    Reseller log in
                                </Link>
                            </li>
                        </ul>
                    </div>

                    <div>
                        <p className="mb-3 text-xs font-semibold uppercase tracking-[0.18em] text-blush-300">Reach us</p>
                        <ul className="space-y-2.5 text-sm text-cream-200/75">
                            <li className="flex items-start gap-2.5">
                                <Phone className="mt-0.5 h-4 w-4 shrink-0 text-blush-300" />
                                {brand?.phone}
                            </li>
                            <li className="flex items-start gap-2.5">
                                <Mail className="mt-0.5 h-4 w-4 shrink-0 text-blush-300" />
                                {brand?.email}
                            </li>
                            <li className="flex items-start gap-2.5">
                                <Facebook className="mt-0.5 h-4 w-4 shrink-0 text-blush-300" />
                                {brand?.facebook}
                            </li>
                            <li className="flex items-start gap-2.5">
                                <MapPin className="mt-0.5 h-4 w-4 shrink-0 text-blush-300" />
                                {brand?.address}
                            </li>
                        </ul>
                    </div>
                </div>

                <div className="border-t border-cream-200/10 px-4 py-5 text-center text-xs text-cream-200/50">
                    © {new Date().getFullYear()} {brand?.name}. Made with cream, cocoa and a lot of care.
                </div>
            </footer>
        </div>
    );
}
