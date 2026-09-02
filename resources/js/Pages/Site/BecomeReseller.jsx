import { Head, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, HandCoins, Loader2, ShieldCheck, Sparkles } from 'lucide-react';
import SiteLayout from '@/Layouts/SiteLayout';
import { Button, Field, Input, Textarea } from '@/Components/Lileu/ui';

const PERKS = [
    ['Wholesale pricing', 'Order at reseller rates with a clear margin printed on every cup.'],
    ['Half now, half later', 'Reserve your batch with a 50% downpayment and settle the rest before pickup.'],
    ['Real receipts', 'Every payment gets its own numbered receipt you can print or download.'],
    ['Direct line to us', 'Message the kitchen from your portal about any order, any time.'],
];

export default function BecomeReseller({ downpaymentPercent }) {
    const { brand } = usePage().props;

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        business_name: '',
        email: '',
        phone: '',
        city: '',
        address: '',
        facebook: '',
        why_reseller: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('reseller.apply.store'));
    };

    return (
        <SiteLayout>
            <Head title="Become a reseller" />

            <div className="bg-cream-fade">
                <div className="mx-auto max-w-6xl px-4 py-14 sm:px-6 sm:py-20">
                    <div className="grid gap-12 lg:grid-cols-[0.9fr_1.1fr]">
                        <div>
                            <span className="badge bg-blush-100 text-blush-600">
                                <Sparkles className="h-3.5 w-3.5" /> Reseller programme
                            </span>

                            <h1 className="mt-5 font-display text-3xl font-semibold leading-tight tracking-tight text-chocolate-700 sm:text-4xl">
                                Sell {brand?.name} in your own corner of the island.
                            </h1>

                            <p className="mt-4 text-base leading-relaxed text-chocolate-500">
                                Fill this in once. We review every application by hand and usually get back to you
                                within a couple of days. Your account is created right away so you can check your
                                status any time.
                            </p>

                            <ul className="mt-8 space-y-4">
                                {PERKS.map(([title, body]) => (
                                    <li key={title} className="flex gap-3">
                                        <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0 text-success" />
                                        <span>
                                            <span className="block font-semibold text-chocolate-700">{title}</span>
                                            <span className="block text-sm text-chocolate-400">{body}</span>
                                        </span>
                                    </li>
                                ))}
                            </ul>

                            <div className="mt-8 flex items-center gap-3 rounded-2xl border border-caramel/25 bg-caramel-soft/20 px-5 py-4">
                                <HandCoins className="h-5 w-5 shrink-0 text-caramel-dark" />
                                <p className="text-sm text-chocolate-600">
                                    Orders are confirmed with a{' '}
                                    <strong className="font-semibold">{downpaymentPercent}% downpayment</strong> paid
                                    through QR Ph. The balance stays clearly visible until it is settled.
                                </p>
                            </div>
                        </div>

                        <form onSubmit={submit} className="card card-pad h-fit">
                            <h2 className="font-display text-xl font-semibold text-chocolate-700">
                                Your application
                            </h2>
                            <p className="mt-1 text-sm text-chocolate-400">
                                All fields marked with <span className="text-cherry">*</span> are required.
                            </p>

                            <div className="mt-6 grid gap-4 sm:grid-cols-2">
                                <Field label="Full name" required error={errors.name} className="sm:col-span-2">
                                    <Input
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="Juan Dela Cruz"
                                        autoComplete="name"
                                    />
                                </Field>

                                <Field label="Business / page name" error={errors.business_name}>
                                    <Input
                                        value={data.business_name}
                                        onChange={(e) => setData('business_name', e.target.value)}
                                        placeholder="Sweet Corner PH"
                                    />
                                </Field>

                                <Field label="Facebook page" error={errors.facebook}>
                                    <Input
                                        value={data.facebook}
                                        onChange={(e) => setData('facebook', e.target.value)}
                                        placeholder="facebook.com/yourpage"
                                    />
                                </Field>

                                <Field label="Email" required error={errors.email}>
                                    <Input
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        placeholder="you@example.com"
                                        autoComplete="email"
                                    />
                                </Field>

                                <Field label="Mobile number" required error={errors.phone}>
                                    <Input
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                        placeholder="0917 000 0000"
                                        autoComplete="tel"
                                    />
                                </Field>

                                <Field label="City / municipality" error={errors.city}>
                                    <Input
                                        value={data.city}
                                        onChange={(e) => setData('city', e.target.value)}
                                        placeholder="Mabinay"
                                    />
                                </Field>

                                <Field label="Complete address" error={errors.address}>
                                    <Input
                                        value={data.address}
                                        onChange={(e) => setData('address', e.target.value)}
                                        placeholder="Street, barangay, city"
                                    />
                                </Field>

                                <Field
                                    label="How do you plan to sell?"
                                    error={errors.why_reseller}
                                    hint="A sentence or two is plenty — it helps us set your catalog."
                                    className="sm:col-span-2"
                                >
                                    <Textarea
                                        rows={3}
                                        value={data.why_reseller}
                                        onChange={(e) => setData('why_reseller', e.target.value)}
                                        placeholder="I sell online and do weekend bazaars around Mabinay."
                                    />
                                </Field>

                                <Field label="Password" required error={errors.password}>
                                    <Input
                                        type="password"
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        autoComplete="new-password"
                                    />
                                </Field>

                                <Field label="Confirm password" required>
                                    <Input
                                        type="password"
                                        value={data.password_confirmation}
                                        onChange={(e) => setData('password_confirmation', e.target.value)}
                                        autoComplete="new-password"
                                    />
                                </Field>
                            </div>

                            <Button type="submit" disabled={processing} className="mt-6 w-full py-3 text-base">
                                {processing ? (
                                    <>
                                        <Loader2 className="h-4 w-4 animate-spin" /> Sending…
                                    </>
                                ) : (
                                    'Submit application'
                                )}
                            </Button>

                            <p className="mt-4 flex items-start gap-2 text-xs leading-relaxed text-chocolate-400">
                                <ShieldCheck className="mt-0.5 h-4 w-4 shrink-0 text-success" />
                                We only use these details to review your application and run your orders. Nothing is
                                shared with anyone else.
                            </p>
                        </form>
                    </div>
                </div>
            </div>
        </SiteLayout>
    );
}
