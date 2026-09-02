import { Head, Link, useForm } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';
import { Button } from '@/Components/Lileu/ui';

export default function VerifyEmail({ status }) {
    const { post, processing } = useForm({});

    const submit = (e) => {
        e.preventDefault();
        post(route('verification.send'));
    };

    return (
        <GuestLayout
            title="Verify your email"
            description="We sent you a link. Click it to finish setting up your account."
        >
            <Head title="Email verification" />

            {status === 'verification-link-sent' && (
                <div className="mb-4 rounded-xl bg-success-light px-4 py-3 text-sm font-medium text-success">
                    A fresh verification link has been sent to your email address.
                </div>
            )}

            <form onSubmit={submit} className="flex items-center justify-between gap-3">
                <Button type="submit" disabled={processing}>
                    Resend verification email
                </Button>

                <Link
                    href={route('logout')}
                    method="post"
                    as="button"
                    className="text-sm font-medium text-chocolate-500 underline decoration-blush-300 decoration-2 underline-offset-2 transition hover:text-chocolate-700"
                >
                    Log out
                </Link>
            </form>
        </GuestLayout>
    );
}
