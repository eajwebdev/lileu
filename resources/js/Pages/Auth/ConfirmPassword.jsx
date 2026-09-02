import { Head, useForm } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';
import { Button, Field, Input } from '@/Components/Lileu/ui';

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({ password: '' });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.confirm'), { onFinish: () => reset('password') });
    };

    return (
        <GuestLayout
            title="Confirm your password"
            description="This is a secure area — please confirm your password to continue."
        >
            <Head title="Confirm password" />

            <form onSubmit={submit} className="space-y-4">
                <Field label="Password" error={errors.password}>
                    <Input
                        type="password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        autoComplete="current-password"
                        autoFocus
                    />
                </Field>

                <Button type="submit" disabled={processing} className="w-full py-3">
                    Confirm
                </Button>
            </form>
        </GuestLayout>
    );
}
