import { Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import clsx from 'clsx';
import { Pencil, Plus, Search, UsersRound } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import {
    Badge,
    Button,
    Card,
    EmptyState,
    Field,
    Input,
    Modal,
    Money,
    Pagination,
    ResellerStatusBadge,
    Select,
    Textarea,
    Toggle,
} from '@/Components/Lileu/ui';

/** How a seller works with us — shown next to their approval status. */
const ENGAGEMENT = {
    reseller: ['Buys wholesale', 'chocolate'],
    consignment: ['Consignment', 'caramel'],
    both: ['Wholesale + consignment', 'blush'],
};

const TABS = [
    ['', 'All'],
    ['pending', 'Pending'],
    ['approved', 'Approved'],
    ['suspended', 'Suspended'],
    ['rejected', 'Rejected'],
];

export default function Index({ resellers, filters, counts }) {
    const [term, setTerm] = useState(filters.q ?? '');
    const [adding, setAdding] = useState(false);
    const [editing, setEditing] = useState(null);

    const apply = (next) =>
        router.get(route('admin.resellers.index'), { ...filters, ...next }, { preserveState: true, replace: true });

    return (
        <AdminLayout
            title="Sellers"
            subtitle="Applications, partners you added yourself, and what they owe."
            action={
                <Button onClick={() => setAdding(true)}>
                    <Plus className="h-4 w-4" /> Add a seller
                </Button>
            }
        >
            <Card className="card-pad">
                <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <div className="flex flex-wrap gap-1.5 rounded-xl border border-cream-300 bg-cream-50 p-1">
                        {TABS.map(([value, label]) => (
                            <button
                                key={label}
                                type="button"
                                onClick={() => apply({ status: value || undefined })}
                                className={clsx(
                                    'rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                                    (filters.status ?? '') === value
                                        ? 'bg-chocolate-700 text-cream-100'
                                        : 'text-chocolate-500 hover:bg-cream-200',
                                )}
                            >
                                {label}
                                {value === 'pending' && counts.pending > 0 && (
                                    <span className="ml-1.5 rounded-full bg-blush-300 px-1.5 py-0.5 text-[10px] text-chocolate-800">
                                        {counts.pending}
                                    </span>
                                )}
                            </button>
                        ))}
                    </div>

                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            apply({ q: term || undefined });
                        }}
                        className="relative min-w-52"
                    >
                        <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-chocolate-300" />
                        <Input
                            value={term}
                            onChange={(e) => setTerm(e.target.value)}
                            placeholder="Name, business or code…"
                            className="pl-10"
                        />
                    </form>
                </div>

                {resellers.data.length === 0 ? (
                    <EmptyState
                        icon={UsersRound}
                        title="No resellers here"
                        description="Applications from the public site land in the Pending tab."
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="table-lileu min-w-full">
                            <thead>
                                <tr>
                                    <th>Reseller</th>
                                    <th>Contact</th>
                                    <th>Area</th>
                                    <th>Status</th>
                                    <th className="text-center">Orders</th>
                                    <th className="text-right">Lifetime value</th>
                                    <th className="text-right">Edit</th>
                                </tr>
                            </thead>
                            <tbody>
                                {resellers.data.map((reseller) => (
                                    <tr key={reseller.id} className="transition hover:bg-cream-50">
                                        <td>
                                            <Link
                                                href={route('admin.resellers.show', reseller.id)}
                                                className="font-medium text-chocolate-700 hover:underline"
                                            >
                                                {reseller.business_name || reseller.name}
                                            </Link>
                                            <p className="font-mono text-[11px] text-chocolate-300">
                                                {reseller.code}
                                                {reseller.discount_percent > 0 && (
                                                    <span className="ml-2 text-blush-500">
                                                        {reseller.discount_percent}% off
                                                    </span>
                                                )}
                                            </p>
                                        </td>
                                        <td className="text-xs">
                                            <p>{reseller.phone}</p>
                                            <p className="text-chocolate-300">{reseller.email}</p>
                                        </td>
                                        <td className="text-xs">{reseller.city ?? '—'}</td>
                                        <td>
                                            <div className="flex flex-wrap gap-1.5">
                                                <ResellerStatusBadge status={reseller.status} />
                                                <Badge tone={(ENGAGEMENT[reseller.engagement] ?? ENGAGEMENT.reseller)[1]}>
                                                    {(ENGAGEMENT[reseller.engagement] ?? ENGAGEMENT.reseller)[0]}
                                                </Badge>
                                            </div>
                                            {reseller.applied_on && (
                                                <p className="mt-1 text-[11px] text-chocolate-300">
                                                    applied {reseller.applied_on}
                                                </p>
                                            )}
                                        </td>
                                        <td className="text-center tabular-nums">{reseller.orders_count}</td>
                                        <td className="text-right font-semibold tabular-nums text-chocolate-700">
                                            <Money value={reseller.lifetime_value} />
                                        </td>
                                        <td>
                                            <div className="flex justify-end">
                                                <button
                                                    type="button"
                                                    onClick={() => setEditing(reseller)}
                                                    className="rounded-lg p-1.5 text-chocolate-400 transition hover:bg-cream-200 hover:text-chocolate-700"
                                                    aria-label={`Edit ${reseller.business_name || reseller.name}`}
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination links={resellers.links} className="mt-6" />
            </Card>

            <AddSellerModal open={adding} onClose={() => setAdding(false)} />

            <EditSellerModal
                open={editing !== null}
                reseller={editing}
                onClose={() => setEditing(null)}
            />
        </AdminLayout>
    );
}

/**
 * Most consignment sellers never see the public application form — a student
 * taking a tray to school has no email and needs no portal login, so the
 * account is optional.
 */
function AddSellerModal({ open, onClose }) {
    const form = useForm({
        name: '',
        business_name: '',
        email: '',
        phone: '',
        city: '',
        address: '',
        engagement: 'consignment',
        discount_percent: 0,
        downpayment_percent: 50,
        admin_notes: '',
        create_login: false,
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('admin.resellers.store'), { onSuccess: () => { form.reset(); onClose(); } });
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            title="Add a seller"
            description="They are approved straight away — no application to review."
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button onClick={submit} disabled={form.processing}>
                        Add seller
                    </Button>
                </>
            }
        >
            <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                <Field label="Full name" required error={form.errors.name} className="sm:col-span-2">
                    <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                </Field>

                <Field label="Business / school" error={form.errors.business_name}>
                    <Input
                        value={form.data.business_name}
                        onChange={(e) => form.setData('business_name', e.target.value)}
                        placeholder="Mabinay National High School"
                    />
                </Field>

                <Field label="Mobile number" required error={form.errors.phone}>
                    <Input value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                </Field>

                <Field label="City / municipality" error={form.errors.city}>
                    <Input
                        value={form.data.city}
                        onChange={(e) => form.setData('city', e.target.value)}
                        placeholder="Mabinay"
                    />
                </Field>

                <Field label="Address" error={form.errors.address}>
                    <Input value={form.data.address} onChange={(e) => form.setData('address', e.target.value)} />
                </Field>

                <Field
                    label="How they sell"
                    required
                    hint="Consignment sellers take stock now and pay after selling."
                    error={form.errors.engagement}
                    className="sm:col-span-2"
                >
                    <Select
                        value={form.data.engagement}
                        onChange={(e) => form.setData('engagement', e.target.value)}
                    >
                        <option value="consignment">Consignment — takes stock, settles later</option>
                        <option value="reseller">Reseller — buys wholesale up front</option>
                        <option value="both">Both</option>
                    </Select>
                </Field>

                {form.data.engagement !== 'consignment' && (
                    <>
                        <Field label="Extra discount %" error={form.errors.discount_percent}>
                            <Input
                                type="number"
                                min="0"
                                max="50"
                                value={form.data.discount_percent}
                                onChange={(e) => form.setData('discount_percent', e.target.value)}
                            />
                        </Field>
                        <Field label="Downpayment %" error={form.errors.downpayment_percent}>
                            <Input
                                type="number"
                                min="0"
                                max="100"
                                value={form.data.downpayment_percent}
                                onChange={(e) => form.setData('downpayment_percent', e.target.value)}
                            />
                        </Field>
                    </>
                )}

                <Field label="Internal notes" error={form.errors.admin_notes} className="sm:col-span-2">
                    <Textarea
                        rows={2}
                        value={form.data.admin_notes}
                        onChange={(e) => form.setData('admin_notes', e.target.value)}
                    />
                </Field>

                <div className="sm:col-span-2">
                    <Toggle
                        checked={form.data.create_login}
                        onChange={(v) => form.setData('create_login', v)}
                        label="Give them a portal login"
                        description="Only needed if they will order online themselves."
                    />
                </div>

                {form.data.create_login && (
                    <>
                        <Field label="Email" required error={form.errors.email} className="sm:col-span-2">
                            <Input
                                type="email"
                                value={form.data.email}
                                onChange={(e) => form.setData('email', e.target.value)}
                            />
                        </Field>
                        <Field label="Password" required error={form.errors.password}>
                            <Input
                                type="password"
                                value={form.data.password}
                                onChange={(e) => form.setData('password', e.target.value)}
                            />
                        </Field>
                        <Field label="Confirm password">
                            <Input
                                type="password"
                                value={form.data.password_confirmation}
                                onChange={(e) => form.setData('password_confirmation', e.target.value)}
                            />
                        </Field>
                    </>
                )}
            </form>
        </Modal>
    );
}

/**
 * Edit a seller in place.
 *
 * Name and email are deliberately absent: they belong to the person's own
 * account, and the status switch lives on their detail page beside the
 * message that explains the change.
 */
function EditSellerModal({ open, reseller, onClose }) {
    const form = useForm({
        business_name: '',
        phone: '',
        city: '',
        address: '',
        engagement: 'reseller',
        discount_percent: 0,
        downpayment_percent: 50,
        admin_notes: '',
    });

    useEffect(() => {
        if (!open || !reseller) return;

        form.clearErrors();
        form.setData({
            business_name: reseller.business_name ?? '',
            phone: reseller.phone ?? '',
            city: reseller.city ?? '',
            address: reseller.address ?? '',
            engagement: reseller.engagement ?? 'reseller',
            discount_percent: reseller.discount_percent ?? 0,
            downpayment_percent: reseller.downpayment_percent ?? 50,
            admin_notes: reseller.admin_notes ?? '',
        });
    }, [open, reseller?.id]);

    if (!reseller) return null;

    const submit = (e) => {
        e.preventDefault();

        form.put(route('admin.resellers.update', reseller.id), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={`Edit ${reseller.business_name || reseller.name}`}
            description={reseller.code}
            footer={
                <>
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" form="edit-seller-form" disabled={form.processing}>
                        Save changes
                    </Button>
                </>
            }
        >
            <form id="edit-seller-form" onSubmit={submit} className="grid gap-3 sm:grid-cols-2">
                <Field label="Business name" error={form.errors.business_name} className="sm:col-span-2">
                    <Input
                        value={form.data.business_name}
                        onChange={(e) => form.setData('business_name', e.target.value)}
                        placeholder={reseller.name}
                    />
                </Field>
                <Field label="Phone" error={form.errors.phone} required>
                    <Input value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                </Field>
                <Field label="City" error={form.errors.city}>
                    <Input value={form.data.city} onChange={(e) => form.setData('city', e.target.value)} />
                </Field>
                <Field label="Address" error={form.errors.address} className="sm:col-span-2">
                    <Textarea
                        rows={2}
                        value={form.data.address}
                        onChange={(e) => form.setData('address', e.target.value)}
                    />
                </Field>
                <Field label="Engagement" error={form.errors.engagement} required className="sm:col-span-2">
                    <Select
                        value={form.data.engagement}
                        onChange={(e) => form.setData('engagement', e.target.value)}
                    >
                        <option value="reseller">Buys wholesale</option>
                        <option value="consignment">Consignment</option>
                        <option value="both">Wholesale + consignment</option>
                    </Select>
                </Field>
                <Field
                    label="Discount %"
                    error={form.errors.discount_percent}
                    hint="Taken off their order subtotal"
                    required
                >
                    <Input
                        type="number"
                        min="0"
                        max="50"
                        value={form.data.discount_percent}
                        onChange={(e) => form.setData('discount_percent', e.target.value)}
                    />
                </Field>
                <Field
                    label="Downpayment %"
                    error={form.errors.downpayment_percent}
                    hint="Required before an order is confirmed"
                    required
                >
                    <Input
                        type="number"
                        min="0"
                        max="100"
                        value={form.data.downpayment_percent}
                        onChange={(e) => form.setData('downpayment_percent', e.target.value)}
                    />
                </Field>
                <Field label="Internal notes" error={form.errors.admin_notes} className="sm:col-span-2">
                    <Textarea
                        rows={3}
                        value={form.data.admin_notes}
                        onChange={(e) => form.setData('admin_notes', e.target.value)}
                        placeholder="Only staff see this."
                    />
                </Field>
            </form>
        </Modal>
    );
}
