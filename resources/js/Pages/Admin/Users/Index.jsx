import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import clsx from 'clsx';
import { Pencil, Plus, ShieldCheck } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Badge, Button, Card, Field, Input, Modal, Select, Toggle } from '@/Components/Lileu/ui';

const ROLE_TONES = {
    admin: 'chocolate',
    cashier: 'caramel',
    reseller: 'blush',
};

export default function Index({ users }) {
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState(null);

    const createForm = useForm({
        name: '',
        email: '',
        phone: '',
        role: 'cashier',
        password: '',
        password_confirmation: '',
    });

    const editForm = useForm({
        name: '',
        email: '',
        phone: '',
        role: 'cashier',
        is_active: true,
        password: '',
        password_confirmation: '',
    });

    const openEdit = (user) => {
        editForm.setData({
            name: user.name,
            email: user.email,
            phone: user.phone ?? '',
            role: user.role,
            is_active: user.is_active,
            password: '',
            password_confirmation: '',
        });
        editForm.clearErrors();
        setEditing(user);
    };

    return (
        <AdminLayout
            title="Users"
            subtitle="Owner, cashier and reseller accounts."
            action={
                <Button onClick={() => setCreating(true)}>
                    <Plus className="h-4 w-4" /> New staff account
                </Button>
            }
        >
            <Card className="card-pad">
                <div className="overflow-x-auto">
                    <table className="table-lileu min-w-full">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th className="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.map((user) => (
                                <tr key={user.id} className="transition hover:bg-cream-50">
                                    <td className="font-medium text-chocolate-700">{user.name}</td>
                                    <td className="text-xs">{user.email}</td>
                                    <td className="text-xs">{user.phone ?? '—'}</td>
                                    <td>
                                        <Badge tone={ROLE_TONES[user.role] ?? 'blush'} className="capitalize">
                                            {user.role}
                                        </Badge>
                                    </td>
                                    <td>
                                        <span
                                            className={clsx(
                                                'badge',
                                                user.is_active
                                                    ? 'bg-success-light text-success'
                                                    : 'bg-cherry/10 text-cherry-dark',
                                            )}
                                        >
                                            {user.is_active ? 'Active' : 'Disabled'}
                                        </span>
                                    </td>
                                    <td className="text-xs">{user.joined_on}</td>
                                    <td className="text-right">
                                        <button
                                            type="button"
                                            onClick={() => openEdit(user)}
                                            className="rounded-lg p-1.5 text-chocolate-400 transition hover:bg-cream-200 hover:text-chocolate-700"
                                            aria-label={`Edit ${user.name}`}
                                        >
                                            <Pencil className="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Card>

            {/* Create */}
            <Modal
                open={creating}
                onClose={() => setCreating(false)}
                size="sm"
                title="New staff account"
                description="Cashiers get the POS; admins get everything."
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setCreating(false)}>
                            Cancel
                        </Button>
                        <Button
                            onClick={() =>
                                createForm.post(route('admin.users.store'), {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        createForm.reset();
                                        setCreating(false);
                                    },
                                })
                            }
                            disabled={createForm.processing}
                        >
                            Create account
                        </Button>
                    </>
                }
            >
                <div className="grid gap-4">
                    <Field label="Name" required error={createForm.errors.name}>
                        <Input
                            value={createForm.data.name}
                            onChange={(e) => createForm.setData('name', e.target.value)}
                        />
                    </Field>
                    <Field label="Email" required error={createForm.errors.email}>
                        <Input
                            type="email"
                            value={createForm.data.email}
                            onChange={(e) => createForm.setData('email', e.target.value)}
                        />
                    </Field>
                    <Field label="Phone" error={createForm.errors.phone}>
                        <Input
                            value={createForm.data.phone}
                            onChange={(e) => createForm.setData('phone', e.target.value)}
                        />
                    </Field>
                    <Field label="Role" required error={createForm.errors.role}>
                        <Select
                            value={createForm.data.role}
                            onChange={(e) => createForm.setData('role', e.target.value)}
                        >
                            <option value="cashier">Cashier</option>
                            <option value="admin">Admin</option>
                        </Select>
                    </Field>
                    <Field label="Password" required error={createForm.errors.password}>
                        <Input
                            type="password"
                            value={createForm.data.password}
                            onChange={(e) => createForm.setData('password', e.target.value)}
                        />
                    </Field>
                    <Field label="Confirm password" required>
                        <Input
                            type="password"
                            value={createForm.data.password_confirmation}
                            onChange={(e) => createForm.setData('password_confirmation', e.target.value)}
                        />
                    </Field>
                </div>
            </Modal>

            {/* Edit */}
            <Modal
                open={editing !== null}
                onClose={() => setEditing(null)}
                size="sm"
                title={`Edit ${editing?.name ?? ''}`}
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setEditing(null)}>
                            Cancel
                        </Button>
                        <Button
                            onClick={() =>
                                editForm.put(route('admin.users.update', editing.id), {
                                    preserveScroll: true,
                                    onSuccess: () => setEditing(null),
                                })
                            }
                            disabled={editForm.processing}
                        >
                            Save changes
                        </Button>
                    </>
                }
            >
                <div className="grid gap-4">
                    <Field label="Name" required error={editForm.errors.name}>
                        <Input value={editForm.data.name} onChange={(e) => editForm.setData('name', e.target.value)} />
                    </Field>
                    <Field label="Email" required error={editForm.errors.email}>
                        <Input
                            type="email"
                            value={editForm.data.email}
                            onChange={(e) => editForm.setData('email', e.target.value)}
                        />
                    </Field>
                    <Field label="Phone" error={editForm.errors.phone}>
                        <Input value={editForm.data.phone} onChange={(e) => editForm.setData('phone', e.target.value)} />
                    </Field>
                    <Field label="Role" required error={editForm.errors.role}>
                        <Select value={editForm.data.role} onChange={(e) => editForm.setData('role', e.target.value)}>
                            <option value="cashier">Cashier</option>
                            <option value="admin">Admin</option>
                            <option value="reseller">Reseller</option>
                        </Select>
                    </Field>
                    <Toggle
                        checked={editForm.data.is_active}
                        onChange={(v) => editForm.setData('is_active', v)}
                        label="Active"
                        description="Disabled accounts cannot sign in."
                    />
                    <Field
                        label="New password"
                        hint="Leave blank to keep the current one."
                        error={editForm.errors.password}
                    >
                        <Input
                            type="password"
                            value={editForm.data.password}
                            onChange={(e) => editForm.setData('password', e.target.value)}
                        />
                    </Field>
                    <Field label="Confirm new password">
                        <Input
                            type="password"
                            value={editForm.data.password_confirmation}
                            onChange={(e) => editForm.setData('password_confirmation', e.target.value)}
                        />
                    </Field>

                    <p className="flex items-start gap-2 rounded-xl bg-cream-100 px-3 py-2.5 text-xs text-chocolate-400">
                        <ShieldCheck className="mt-0.5 h-4 w-4 shrink-0 text-success" />
                        The last remaining admin cannot be demoted — someone always keeps the keys.
                    </p>
                </div>
            </Modal>
        </AdminLayout>
    );
}
