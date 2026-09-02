import { Head, router, usePage } from '@inertiajs/react';
import { Clock, LogOut, MessageCircleWarning, ShieldOff } from 'lucide-react';
import { Button, Logo, ResellerStatusBadge } from '@/Components/Lileu/ui';

const COPY = {
    pending: {
        icon: Clock,
        title: 'Your application is being reviewed',
        body: 'We read every application by hand and set up your catalog before approving. This usually takes a day or two.',
        tint: 'bg-caramel-soft/25 text-caramel-dark',
    },
    rejected: {
        icon: MessageCircleWarning,
        title: 'We could not approve this application',
        body: 'If you think this was a mistake, or your situation has changed, message us and we will take another look.',
        tint: 'bg-cherry/10 text-cherry-dark',
    },
    suspended: {
        icon: ShieldOff,
        title: 'Your reseller account is on hold',
        body: 'Ordering is paused for now. Please get in touch with us so we can sort it out together.',
        tint: 'bg-cherry/10 text-cherry-dark',
    },
};

export default function Status({ reseller }) {
    const { brand } = usePage().props;
    const copy = COPY[reseller.status] ?? COPY.pending;
    const Icon = copy.icon;

    return (
        <div className="flex min-h-screen items-center justify-center bg-cream-fade px-4 py-14">
            <Head title="Application status" />

            <div className="w-full max-w-lg">
                <div className="mb-6 flex items-center justify-center gap-3">
                    <Logo className="h-11 w-11" />
                    <span className="font-display text-xl font-semibold text-chocolate-700">{brand?.name}</span>
                </div>

                <div className="card card-pad text-center">
                    <span className={`mx-auto flex h-16 w-16 items-center justify-center rounded-2xl ${copy.tint}`}>
                        <Icon className="h-7 w-7" />
                    </span>

                    <div className="mt-5 flex justify-center">
                        <ResellerStatusBadge status={reseller.status} />
                    </div>

                    <h1 className="mt-4 font-display text-2xl font-semibold text-chocolate-700">{copy.title}</h1>
                    <p className="mt-3 text-sm leading-relaxed text-chocolate-500">{copy.body}</p>

                    {reseller.admin_notes && (
                        <p className="mt-5 rounded-2xl border border-cream-300 bg-cream-100 px-4 py-3 text-left text-sm text-chocolate-600">
                            <span className="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-chocolate-400">
                                Note from {brand?.name}
                            </span>
                            {reseller.admin_notes}
                        </p>
                    )}

                    <dl className="mt-6 grid grid-cols-2 gap-3 border-t border-cream-300 pt-5 text-left">
                        <div>
                            <dt className="text-xs font-medium uppercase tracking-[0.14em] text-chocolate-400">
                                Reseller code
                            </dt>
                            <dd className="mt-0.5 font-mono text-sm font-semibold text-chocolate-700">
                                {reseller.code}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-medium uppercase tracking-[0.14em] text-chocolate-400">
                                Applied on
                            </dt>
                            <dd className="mt-0.5 text-sm font-semibold text-chocolate-700">{reseller.applied_at}</dd>
                        </div>
                    </dl>

                    <Button
                        variant="ghost"
                        onClick={() => router.post(route('logout'))}
                        className="mt-6 w-full"
                    >
                        <LogOut className="h-4 w-4" /> Log out
                    </Button>
                </div>

                <p className="mt-5 text-center text-sm text-chocolate-400">
                    Questions? Reach us at {brand?.phone} or {brand?.email}.
                </p>
            </div>
        </div>
    );
}
