import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import clsx from 'clsx';
import { ArrowLeft, Check, Facebook, Mail, MapPin, MessageCircle, Package, Phone } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import {
    Button,
    ButtonLink,
    Card,
    Field,
    Input,
    Money,
    OrderStatusBadge,
    PaymentStatusBadge,
    ResellerStatusBadge,
    Select,
    Textarea,
} from '@/Components/Lileu/ui';

const STATUS_ACTIONS = [
    ['approved', 'Approve', 'success'],
    ['suspended', 'Suspend', 'ghost'],
    ['rejected', 'Reject', 'danger'],
];

export default function Show({ reseller, orders, consignments = [], catalog, assigned }) {
    const assignedMap = Object.fromEntries(assigned.map((a) => [a.product_id, a]));

    const [selection, setSelection] = useState(() =>
        Object.fromEntries(
            catalog.map((p) => [
                p.id,
                {
                    checked: Boolean(assignedMap[p.id]?.is_approved),
                    custom_price: assignedMap[p.id]?.custom_price ?? '',
                },
            ]),
        ),
    );

    const profileForm = useForm({
        business_name: reseller.business_name ?? '',
        engagement: reseller.engagement ?? 'reseller',
        phone: reseller.phone,
        city: reseller.city ?? '',
        address: reseller.address ?? '',
        discount_percent: reseller.discount_percent,
        downpayment_percent: reseller.downpayment_percent,
        admin_notes: reseller.admin_notes ?? '',
    });

    const statusForm = useForm({ status: reseller.status, admin_notes: '', message: '' });
    const catalogForm = useForm({ products: [] });

    const saveCatalog = () => {
        catalogForm.transform(() => ({
            products: Object.entries(selection)
                .filter(([, value]) => value.checked)
                .map(([id, value]) => ({
                    product_id: Number(id),
                    custom_price: value.custom_price === '' ? null : Number(value.custom_price),
                    is_approved: true,
                })),
        }));

        catalogForm.post(route('admin.resellers.products', reseller.id), { preserveScroll: true });
    };

    const setStatus = (status) => {
        statusForm.transform((form) => ({ ...form, status }));
        statusForm.post(route('admin.resellers.status', reseller.id), { preserveScroll: true });
    };

    const approvedCount = Object.values(selection).filter((v) => v.checked).length;

    return (
        <AdminLayout
            title={reseller.business_name || reseller.name}
            subtitle={reseller.code}
            action={
                <Link
                    href={route('admin.resellers.index')}
                    className="inline-flex items-center gap-1.5 text-sm font-medium text-chocolate-400 transition hover:text-chocolate-700"
                >
                    <ArrowLeft className="h-4 w-4" /> All resellers
                </Link>
            }
        >
            <div className="grid gap-5 xl:grid-cols-[1.55fr_1fr] xl:items-start">
                <div className="space-y-5">
                    {/* Profile */}
                    <Card className="card-pad">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <ResellerStatusBadge status={reseller.status} />
                                <h2 className="mt-2 font-display text-xl font-semibold text-chocolate-700">
                                    {reseller.name}
                                </h2>
                                <p className="text-sm text-chocolate-400">
                                    Applied {reseller.applied_on}
                                    {reseller.approved_on ? ` · approved ${reseller.approved_on}` : ''}
                                </p>
                            </div>

                            <div className="flex flex-wrap gap-2">
                                {STATUS_ACTIONS.filter(([value]) => value !== reseller.status).map(
                                    ([value, label, variant]) => (
                                        <Button
                                            key={value}
                                            variant={variant}
                                            onClick={() => setStatus(value)}
                                            disabled={statusForm.processing}
                                            className="px-3 py-1.5 text-xs"
                                        >
                                            {value === 'approved' && <Check className="h-3.5 w-3.5" />}
                                            {label}
                                        </Button>
                                    ),
                                )}
                            </div>
                        </div>

                        <div className="mt-5 grid gap-3 border-t border-cream-200 pt-5 text-sm sm:grid-cols-2">
                            <p className="flex items-center gap-2 text-chocolate-600">
                                <Phone className="h-4 w-4 text-blush-500" /> {reseller.phone}
                            </p>
                            <p className="flex items-center gap-2 text-chocolate-600">
                                <Mail className="h-4 w-4 text-blush-500" /> {reseller.email}
                            </p>
                            {reseller.address && (
                                <p className="flex items-center gap-2 text-chocolate-600">
                                    <MapPin className="h-4 w-4 text-blush-500" /> {reseller.address}
                                </p>
                            )}
                            {reseller.facebook && (
                                <p className="flex items-center gap-2 text-chocolate-600">
                                    <Facebook className="h-4 w-4 text-blush-500" /> {reseller.facebook}
                                </p>
                            )}
                        </div>

                        {reseller.why_reseller && (
                            <div className="mt-4 rounded-2xl border border-cream-300 bg-cream-50 px-4 py-3">
                                <p className="text-xs font-semibold uppercase tracking-[0.14em] text-chocolate-400">
                                    Why they applied
                                </p>
                                <p className="mt-1 text-sm text-chocolate-600">{reseller.why_reseller}</p>
                            </div>
                        )}

                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                profileForm.put(route('admin.resellers.update', reseller.id), {
                                    preserveScroll: true,
                                });
                            }}
                            className="mt-5 grid gap-4 border-t border-cream-200 pt-5 sm:grid-cols-2"
                        >
                            <Field label="Business name" error={profileForm.errors.business_name}>
                                <Input
                                    value={profileForm.data.business_name}
                                    onChange={(e) => profileForm.setData('business_name', e.target.value)}
                                />
                            </Field>
                            <Field label="Phone" error={profileForm.errors.phone}>
                                <Input
                                    value={profileForm.data.phone}
                                    onChange={(e) => profileForm.setData('phone', e.target.value)}
                                />
                            </Field>
                            <Field
                                label="How they sell"
                                error={profileForm.errors.engagement}
                                className="sm:col-span-2"
                            >
                                <Select
                                    value={profileForm.data.engagement}
                                    onChange={(e) => profileForm.setData('engagement', e.target.value)}
                                >
                                    <option value="reseller">Reseller — buys wholesale up front</option>
                                    <option value="consignment">Consignment — takes stock, settles later</option>
                                    <option value="both">Both</option>
                                </Select>
                            </Field>
                            <Field label="City" error={profileForm.errors.city}>
                                <Input
                                    value={profileForm.data.city}
                                    onChange={(e) => profileForm.setData('city', e.target.value)}
                                />
                            </Field>
                            <Field label="Address" error={profileForm.errors.address}>
                                <Input
                                    value={profileForm.data.address}
                                    onChange={(e) => profileForm.setData('address', e.target.value)}
                                />
                            </Field>
                            <Field
                                label="Extra discount %"
                                hint="Applied on top of wholesale pricing."
                                error={profileForm.errors.discount_percent}
                            >
                                <Input
                                    type="number"
                                    min="0"
                                    max="50"
                                    value={profileForm.data.discount_percent}
                                    onChange={(e) => profileForm.setData('discount_percent', e.target.value)}
                                />
                            </Field>
                            <Field
                                label="Downpayment %"
                                hint="Required before an order is confirmed."
                                error={profileForm.errors.downpayment_percent}
                            >
                                <Input
                                    type="number"
                                    min="0"
                                    max="100"
                                    value={profileForm.data.downpayment_percent}
                                    onChange={(e) => profileForm.setData('downpayment_percent', e.target.value)}
                                />
                            </Field>
                            <Field label="Internal notes" className="sm:col-span-2" error={profileForm.errors.admin_notes}>
                                <Textarea
                                    rows={2}
                                    value={profileForm.data.admin_notes}
                                    onChange={(e) => profileForm.setData('admin_notes', e.target.value)}
                                />
                            </Field>
                            <div className="sm:col-span-2">
                                <Button type="submit" disabled={profileForm.processing}>
                                    Save profile
                                </Button>
                            </div>
                        </form>
                    </Card>

                    {/* Approved catalog */}
                    <Card className="card-pad">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 className="font-display text-lg font-semibold text-chocolate-700">
                                    Approved products
                                </h2>
                                <p className="text-sm text-chocolate-400">
                                    {approvedCount === 0
                                        ? 'None selected — they see the full wholesale catalog.'
                                        : `${approvedCount} product${approvedCount === 1 ? '' : 's'} selected.`}
                                </p>
                            </div>
                            <Button onClick={saveCatalog} disabled={catalogForm.processing}>
                                <Package className="h-4 w-4" /> Save catalog
                            </Button>
                        </div>

                        <div className="mt-4 overflow-x-auto">
                            <table className="table-lileu min-w-full">
                                <thead>
                                    <tr>
                                        <th className="w-10" />
                                        <th>Product</th>
                                        <th className="text-right">Standard</th>
                                        <th className="text-right">Retail</th>
                                        <th className="w-36 text-right">Custom price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {catalog.map((product) => {
                                        const row = selection[product.id] ?? { checked: false, custom_price: '' };

                                        return (
                                            <tr key={product.id} className={clsx(row.checked && 'bg-blush-50/60')}>
                                                <td>
                                                    <input
                                                        type="checkbox"
                                                        checked={row.checked}
                                                        onChange={(e) =>
                                                            setSelection((prev) => ({
                                                                ...prev,
                                                                [product.id]: {
                                                                    ...row,
                                                                    checked: e.target.checked,
                                                                },
                                                            }))
                                                        }
                                                        className="h-4 w-4 rounded border-cream-300 text-chocolate-700 focus:ring-blush-300"
                                                        aria-label={`Approve ${product.name}`}
                                                    />
                                                </td>
                                                <td>
                                                    <p className="font-medium text-chocolate-700">{product.name}</p>
                                                    <p className="font-mono text-[11px] text-chocolate-300">
                                                        {product.sku}
                                                    </p>
                                                </td>
                                                <td className="text-right tabular-nums">
                                                    <Money value={product.reseller_price} />
                                                </td>
                                                <td className="text-right tabular-nums text-chocolate-400">
                                                    <Money value={product.retail_price} />
                                                </td>
                                                <td>
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        placeholder="standard"
                                                        value={row.custom_price ?? ''}
                                                        disabled={!row.checked}
                                                        onChange={(e) =>
                                                            setSelection((prev) => ({
                                                                ...prev,
                                                                [product.id]: {
                                                                    ...row,
                                                                    custom_price: e.target.value,
                                                                },
                                                            }))
                                                        }
                                                        className="py-1.5 text-right text-sm"
                                                    />
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                </div>

                {/* Side */}
                <div className="space-y-4 xl:sticky xl:top-6">
                    <Card className="card-pad">
                        <dl className="space-y-3">
                            <div>
                                <dt className="text-xs font-semibold uppercase tracking-[0.14em] text-chocolate-400">
                                    Lifetime value
                                </dt>
                                <dd className="font-display text-2xl font-semibold text-chocolate-700">
                                    <Money value={reseller.lifetime_value} />
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold uppercase tracking-[0.14em] text-chocolate-400">
                                    Consignment to collect
                                </dt>
                                <dd
                                    className={clsx(
                                        'font-display text-2xl font-semibold',
                                        reseller.consignment_due > 0 ? 'text-caramel-dark' : 'text-success',
                                    )}
                                >
                                    <Money value={reseller.consignment_due} />
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold uppercase tracking-[0.14em] text-chocolate-400">
                                    Order balance
                                </dt>
                                <dd
                                    className={clsx(
                                        'font-display text-2xl font-semibold',
                                        reseller.outstanding > 0 ? 'text-caramel-dark' : 'text-success',
                                    )}
                                >
                                    <Money value={reseller.outstanding} />
                                </dd>
                            </div>
                        </dl>

                        <ButtonLink
                            href={route('admin.messages.index', { reseller: reseller.id })}
                            variant="secondary"
                            className="mt-4 w-full"
                        >
                            <MessageCircle className="h-4 w-4" /> Open chat
                        </ButtonLink>
                    </Card>

                    <Card className="card-pad">
                        <h2 className="font-display text-base font-semibold text-chocolate-700">Recent orders</h2>

                        {orders.length === 0 ? (
                            <p className="mt-3 rounded-xl border border-dashed border-cream-300 px-4 py-6 text-center text-sm text-chocolate-400">
                                No orders yet.
                            </p>
                        ) : (
                            <ul className="mt-3 divide-y divide-cream-200">
                                {orders.map((order) => (
                                    <li key={order.number} className="py-3">
                                        <div className="flex items-center justify-between gap-2">
                                            <Link
                                                href={route('admin.orders.show', order.number)}
                                                className="font-mono text-xs font-semibold text-chocolate-700 hover:underline"
                                            >
                                                {order.number}
                                            </Link>
                                            <span className="font-semibold tabular-nums text-chocolate-700">
                                                <Money value={order.total} />
                                            </span>
                                        </div>
                                        <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
                                            <OrderStatusBadge status={order.status} />
                                            <PaymentStatusBadge status={order.payment_status} />
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>

                    {consignments.length > 0 && (
                        <Card className="card-pad">
                            <h2 className="font-display text-base font-semibold text-chocolate-700">
                                Recent consignments
                            </h2>

                            <ul className="mt-3 divide-y divide-cream-200">
                                {consignments.map((c) => (
                                    <li key={c.number} className="py-2.5">
                                        <div className="flex items-center justify-between gap-2">
                                            <Link
                                                href={route('admin.consignments.show', c.number)}
                                                className="font-mono text-xs font-semibold text-chocolate-700 hover:underline"
                                            >
                                                {c.number}
                                            </Link>
                                            <span
                                                className={clsx(
                                                    'font-semibold tabular-nums',
                                                    c.amount_due > 0 ? 'text-caramel-dark' : 'text-success',
                                                )}
                                            >
                                                <Money value={c.amount_due} />
                                            </span>
                                        </div>
                                        <p className="mt-0.5 text-[11px] text-chocolate-300">
                                            {c.issued_on} · {c.quantity_issued} pcs
                                            {c.outstanding > 0 ? ` · ${c.outstanding} still out` : ''}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        </Card>
                    )}

                    <Card className="card-pad">
                        <h2 className="font-display text-base font-semibold text-chocolate-700">
                            Send a note with a status change
                        </h2>
                        <Field label="Message" className="mt-3" error={statusForm.errors.message}>
                            <Textarea
                                rows={3}
                                value={statusForm.data.message}
                                onChange={(e) => statusForm.setData('message', e.target.value)}
                                placeholder="Welcome aboard! Your catalog is ready."
                            />
                        </Field>
                        <Field label="Status" className="mt-3" error={statusForm.errors.status}>
                            <Select
                                value={statusForm.data.status}
                                onChange={(e) => statusForm.setData('status', e.target.value)}
                            >
                                {['pending', 'approved', 'suspended', 'rejected'].map((status) => (
                                    <option key={status} value={status} className="capitalize">
                                        {status}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Button
                            onClick={() => setStatus(statusForm.data.status)}
                            disabled={statusForm.processing}
                            className="mt-4 w-full"
                        >
                            Apply status &amp; send
                        </Button>
                    </Card>
                </div>
            </div>
        </AdminLayout>
    );
}
