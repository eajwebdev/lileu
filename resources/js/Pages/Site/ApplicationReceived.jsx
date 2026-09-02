import { Head, usePage } from '@inertiajs/react';
import { Clock, Mail, PartyPopper } from 'lucide-react';
import SiteLayout from '@/Layouts/SiteLayout';
import { ButtonLink } from '@/Components/Lileu/ui';

export default function ApplicationReceived() {
    const { brand } = usePage().props;

    return (
        <SiteLayout>
            <Head title="Application received" />

            <div className="mx-auto max-w-2xl px-4 py-20 text-center sm:px-6 sm:py-28">
                <span className="mx-auto flex h-20 w-20 items-center justify-center rounded-3xl bg-blush-100 text-blush-600">
                    <PartyPopper className="h-9 w-9" />
                </span>

                <h1 className="mt-7 font-display text-3xl font-semibold tracking-tight text-chocolate-700 sm:text-4xl">
                    Application received! 💗
                </h1>

                <p className="mt-4 text-base leading-relaxed text-chocolate-500">
                    Thank you for wanting to grow with {brand?.name}. Your account is ready — log in any time to
                    check where your application stands.
                </p>

                <div className="mt-8 grid gap-3 text-left sm:grid-cols-2">
                    <div className="rounded-2xl border border-cream-300 bg-vanilla p-5">
                        <Clock className="h-5 w-5 text-caramel-dark" />
                        <p className="mt-3 font-semibold text-chocolate-700">Review takes a day or two</p>
                        <p className="mt-1 text-sm text-chocolate-400">
                            We read every application by hand and set your catalog before approving.
                        </p>
                    </div>
                    <div className="rounded-2xl border border-cream-300 bg-vanilla p-5">
                        <Mail className="h-5 w-5 text-blush-500" />
                        <p className="mt-3 font-semibold text-chocolate-700">We will be in touch</p>
                        <p className="mt-1 text-sm text-chocolate-400">
                            Once approved, your portal unlocks with wholesale pricing and ordering.
                        </p>
                    </div>
                </div>

                <div className="mt-9 flex flex-wrap justify-center gap-3">
                    <ButtonLink href={route('login')} className="px-6 py-3 text-base">
                        Log in to check status
                    </ButtonLink>
                    <ButtonLink href={route('products.index')} variant="ghost" className="px-6 py-3 text-base">
                        Browse the menu
                    </ButtonLink>
                </div>
            </div>
        </SiteLayout>
    );
}
