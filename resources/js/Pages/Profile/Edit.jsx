import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, KeyRound, Save, UserRound } from 'lucide-react';
import { Button, Card, Field, FlashToasts, Input, Logo } from '@/Components/Lileu/ui';

export default function Edit({ mustVerifyEmail, status }) {
    const { auth, brand } = usePage().props;

    const profile = useForm({
        name: auth.user.name,
        email: auth.user.email,
    });

    const password = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    return (
        <div className="min-h-screen bg-cream-fade py-10">
            <Head title="My account" />
            <FlashToasts />

            <div className="mx-auto max-w-2xl px-4">
                <div className="mb-7 flex items-center justify-between gap-4">
                    <Link href={route('home')} className="flex items-center gap-2.5">
                        <Logo className="h-10 w-10" />
                        <span className="font-display text-lg font-semibold text-chocolate-700">{brand?.name}</span>
                    </Link>

                    <Link
                        href={route('dashboard')}
                        className="inline-flex items-center gap-1.5 text-sm font-medium text-chocolate-400 transition hover:text-chocolate-700"
                    >
                        <ArrowLeft className="h-4 w-4" /> Back
                    </Link>
                </div>

                <h1 className="font-display text-2xl font-semibold tracking-tight text-chocolate-700">My account</h1>
                <p className="mt-1 text-sm text-chocolate-400">
                    Signed in as <span className="capitalize">{auth.user.role}</span>.
                </p>

                {status === 'profile-updated' && (
                    <p className="mt-4 rounded-xl bg-success-light px-4 py-3 text-sm font-medium text-success">
                        Profile saved.
                    </p>
                )}

                <Card className="card-pad mt-6">
                    <h2 className="flex items-center gap-2 font-display text-lg font-semibold text-chocolate-700">
                        <UserRound className="h-5 w-5 text-blush-500" /> Profile
                    </h2>

                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            profile.patch(route('profile.update'), { preserveScroll: true });
                        }}
                        className="mt-5 space-y-4"
                    >
                        <Field label="Name" error={profile.errors.name}>
                            <Input
                                value={profile.data.name}
                                onChange={(e) => profile.setData('name', e.target.value)}
                                autoComplete="name"
                            />
                        </Field>

                        <Field label="Email" error={profile.errors.email}>
                            <Input
                                type="email"
                                value={profile.data.email}
                                onChange={(e) => profile.setData('email', e.target.value)}
                                autoComplete="username"
                            />
                        </Field>

                        {mustVerifyEmail && auth.user.email_verified_at === null && (
                            <p className="rounded-xl bg-caramel-soft/20 px-4 py-3 text-sm text-caramel-dark">
                                Your email address is not verified.{' '}
                                <Link
                                    href={route('verification.send')}
                                    method="post"
                                    as="button"
                                    className="font-semibold underline underline-offset-2"
                                >
                                    Resend the verification link
                                </Link>
                            </p>
                        )}

                        <Button type="submit" disabled={profile.processing}>
                            <Save className="h-4 w-4" /> Save profile
                        </Button>
                    </form>
                </Card>

                <Card className="card-pad mt-4">
                    <h2 className="flex items-center gap-2 font-display text-lg font-semibold text-chocolate-700">
                        <KeyRound className="h-5 w-5 text-blush-500" /> Password
                    </h2>
                    <p className="mt-1 text-sm text-chocolate-400">
                        Use a long, unique password to keep your account safe.
                    </p>

                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            password.put(route('password.update'), {
                                preserveScroll: true,
                                onSuccess: () => password.reset(),
                            });
                        }}
                        className="mt-5 space-y-4"
                    >
                        <Field label="Current password" error={password.errors.current_password}>
                            <Input
                                type="password"
                                value={password.data.current_password}
                                onChange={(e) => password.setData('current_password', e.target.value)}
                                autoComplete="current-password"
                            />
                        </Field>

                        <Field label="New password" error={password.errors.password}>
                            <Input
                                type="password"
                                value={password.data.password}
                                onChange={(e) => password.setData('password', e.target.value)}
                                autoComplete="new-password"
                            />
                        </Field>

                        <Field label="Confirm new password" error={password.errors.password_confirmation}>
                            <Input
                                type="password"
                                value={password.data.password_confirmation}
                                onChange={(e) => password.setData('password_confirmation', e.target.value)}
                                autoComplete="new-password"
                            />
                        </Field>

                        <Button type="submit" disabled={password.processing}>
                            <Save className="h-4 w-4" /> Update password
                        </Button>
                    </form>
                </Card>
            </div>
        </div>
    );
}
