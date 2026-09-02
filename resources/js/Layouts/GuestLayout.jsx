import { Link, usePage } from '@inertiajs/react';
import { FlashToasts, Logo } from '@/Components/Lileu/ui';

export default function GuestLayout({ children, title, description }) {
    const { brand } = usePage().props;

    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-cream-fade px-4 py-10">
            <FlashToasts />

            <Link href={route('home')} className="mb-7 flex items-center gap-3">
                <Logo className="h-12 w-12" />
                <span className="leading-tight">
                    <span className="block font-display text-xl font-semibold tracking-tight text-chocolate-700">
                        {brand?.name}
                    </span>
                    <span className="block text-[11px] font-medium uppercase tracking-[0.16em] text-blush-500">
                        {brand?.tagline}
                    </span>
                </span>
            </Link>

            <div className="w-full max-w-md">
                <div className="card card-pad">
                    {title && (
                        <div className="mb-6">
                            <h1 className="font-display text-2xl font-semibold tracking-tight text-chocolate-700">
                                {title}
                            </h1>
                            {description && (
                                <p className="mt-1.5 text-sm leading-relaxed text-chocolate-400">{description}</p>
                            )}
                        </div>
                    )}

                    {children}
                </div>

                <p className="mt-5 text-center text-sm text-chocolate-400">
                    Not a reseller yet?{' '}
                    <Link
                        href={route('reseller.apply')}
                        className="font-semibold text-chocolate-600 underline decoration-blush-300 decoration-2 underline-offset-2 transition hover:text-chocolate-800"
                    >
                        Apply to join
                    </Link>
                </p>
            </div>
        </div>
    );
}
