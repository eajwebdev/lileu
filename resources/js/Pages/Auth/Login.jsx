import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import GuestLayout from '@/Layouts/GuestLayout';
import { Button, Field, Input } from '@/Components/Lileu/ui';

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    return (
        <GuestLayout title="Welcome back" description="Sign in to your portal, counter or back office.">
            <Head title="Log in" />

            {status && (
                <div className="mb-4 rounded-xl bg-success-light px-4 py-3 text-sm font-medium text-success">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-4">
                <Field label="Email" error={errors.email}>
                    <Input
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        autoComplete="username"
                        autoFocus
                    />
                </Field>

                <Field label="Password" error={errors.password}>
                    <Input
                        type="password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        autoComplete="current-password"
                    />
                </Field>

                <div className="flex items-center justify-between">
                    <label className="flex cursor-pointer items-center gap-2 text-sm text-chocolate-500">
                        <input
                            type="checkbox"
                            checked={data.remember}
                            onChange={(e) => setData('remember', e.target.checked)}
                            className="h-4 w-4 rounded border-cream-300 text-chocolate-700 focus:ring-blush-300"
                        />
                        Remember me
                    </label>

                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            className="text-sm font-medium text-chocolate-500 underline decoration-blush-300 decoration-2 underline-offset-2 transition hover:text-chocolate-700"
                        >
                            Forgot password?
                        </Link>
                    )}
                </div>

                <Button type="submit" disabled={processing} className="w-full py-3 text-base">
                    {processing ? (
                        <>
                            <Loader2 className="h-4 w-4 animate-spin" /> Signing in…
                        </>
                    ) : (
                        'Log in'
                    )}
                </Button>
            </form>
        </GuestLayout>
    );
}
