import { Head, useForm } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';
import { Button, Field, Input } from '@/Components/Lileu/ui';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <GuestLayout
            title="Forgot your password?"
            description="Tell us your email and we will send a reset link."
        >
            <Head title="Forgot password" />

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
                        autoFocus
                    />
                </Field>

                <Button type="submit" disabled={processing} className="w-full py-3">
                    Email password reset link
                </Button>
            </form>
        </GuestLayout>
    );
}
