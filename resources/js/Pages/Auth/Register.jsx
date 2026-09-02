import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowRight, Loader2 } from 'lucide-react';
import GuestLayout from '@/Layouts/GuestLayout';
import { Button, Field, Input } from '@/Components/Lileu/ui';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('register'), { onFinish: () => reset('password', 'password_confirmation') });
    };

    return (
        <GuestLayout
            title="Create an account"
            description="Applying as a reseller? Use the full application form so we can set up your catalog."
        >
            <Head title="Register" />

            <Link
                href={route('reseller.apply')}
                className="mb-5 flex items-center justify-between gap-3 rounded-2xl border border-blush-200 bg-blush-50 px-4 py-3 transition hover:shadow-soft"
            >
                <span className="text-sm font-medium text-chocolate-700">
                    Apply as a reseller instead
                    <span className="mt-0.5 block text-xs font-normal text-chocolate-400">
                        Wholesale pricing, ordering and receipts.
                    </span>
                </span>
                <ArrowRight className="h-4 w-4 shrink-0 text-blush-600" />
            </Link>

            <form onSubmit={submit} className="space-y-4">
                <Field label="Name" error={errors.name}>
                    <Input
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        autoComplete="name"
                        autoFocus
                    />
                </Field>

                <Field label="Email" error={errors.email}>
                    <Input
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        autoComplete="username"
                    />
                </Field>

                <Field label="Password" error={errors.password}>
                    <Input
                        type="password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        autoComplete="new-password"
                    />
                </Field>

                <Field label="Confirm password" error={errors.password_confirmation}>
                    <Input
                        type="password"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        autoComplete="new-password"
                    />
                </Field>

                <Button type="submit" disabled={processing} className="w-full py-3 text-base">
                    {processing ? (
                        <>
                            <Loader2 className="h-4 w-4 animate-spin" /> Creating account…
                        </>
                    ) : (
                        'Create account'
                    )}
                </Button>

                <p className="text-center text-sm text-chocolate-400">
                    Already registered?{' '}
                    <Link
                        href={route('login')}
                        className="font-semibold text-chocolate-600 underline decoration-blush-300 decoration-2 underline-offset-2"
                    >
                        Log in
                    </Link>
                </p>
            </form>
        </GuestLayout>
    );
}
