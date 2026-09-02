import { useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Copy, Save } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Button, Card, Field, Input, Select, Toggle } from '@/Components/Lileu/ui';

export default function Edit({ settings, paymongo }) {
    const { brand } = usePage().props;

    const form = useForm({
        business_name: settings.name ?? '',
        business_tagline: settings.tagline ?? '',
        business_phone: settings.phone ?? '',
        business_email: settings.email ?? '',
        business_address: settings.address ?? '',
        business_facebook: settings.facebook ?? '',
        business_website: settings.website ?? '',
        business_logo: settings.logo ?? '',
        receipt_prefix: settings.receipt_prefix ?? 'LE',
        order_prefix: settings.order_prefix ?? 'RE',
        receipt_show_logo: Boolean(settings.receipt_show_logo),
        receipt_footer: settings.receipt_footer ?? '',
        receipt_paper: settings.receipt_paper ?? 'a4',
        default_downpayment_percent: settings.default_downpayment_percent ?? 50,
    });

    const submit = (e) => {
        e.preventDefault();
        form.put(route('admin.settings.update'), { preserveScroll: true });
    };

    const today = new Date();
    const sampleReceipt = `${form.data.receipt_prefix}-${today.getFullYear()}${String(today.getMonth() + 1).padStart(2, '0')}${String(today.getDate()).padStart(2, '0')}-001301`;
    const sampleOrder = `${form.data.order_prefix}-${today.getFullYear()}-000012`;

    return (
        <AdminLayout
            title="Brand & receipts"
            subtitle="Everything the public site, portal and receipts read their identity from."
            action={
                <Button onClick={submit} disabled={form.processing}>
                    <Save className="h-4 w-4" /> Save settings
                </Button>
            }
        >
            <form onSubmit={submit} className="grid gap-5 xl:grid-cols-[1.4fr_1fr] xl:items-start">
                <div className="space-y-5">
                    <Card className="card-pad">
                        <h2 className="font-display text-lg font-semibold text-chocolate-700">Business identity</h2>
                        <p className="text-sm text-chocolate-400">
                            Used on the landing page, the portal, every receipt and the POS slip.
                        </p>

                        <div className="mt-5 grid gap-4 sm:grid-cols-2">
                            <Field label="Business name" required error={form.errors.business_name}>
                                <Input
                                    value={form.data.business_name}
                                    onChange={(e) => form.setData('business_name', e.target.value)}
                                />
                            </Field>
                            <Field
                                label="Tagline"
                                hint="Shown under the name, e.g. Est. 2026."
                                error={form.errors.business_tagline}
                            >
                                <Input
                                    value={form.data.business_tagline}
                                    onChange={(e) => form.setData('business_tagline', e.target.value)}
                                />
                            </Field>
                            <Field label="Phone" error={form.errors.business_phone}>
                                <Input
                                    value={form.data.business_phone}
                                    onChange={(e) => form.setData('business_phone', e.target.value)}
                                />
                            </Field>
                            <Field label="Email" error={form.errors.business_email}>
                                <Input
                                    type="email"
                                    value={form.data.business_email}
                                    onChange={(e) => form.setData('business_email', e.target.value)}
                                />
                            </Field>
                            <Field label="Address" error={form.errors.business_address} className="sm:col-span-2">
                                <Input
                                    value={form.data.business_address}
                                    onChange={(e) => form.setData('business_address', e.target.value)}
                                />
                            </Field>
                            <Field label="Facebook" error={form.errors.business_facebook}>
                                <Input
                                    value={form.data.business_facebook}
                                    onChange={(e) => form.setData('business_facebook', e.target.value)}
                                />
                            </Field>
                            <Field label="Website" error={form.errors.business_website}>
                                <Input
                                    value={form.data.business_website}
                                    onChange={(e) => form.setData('business_website', e.target.value)}
                                />
                            </Field>
                            <Field
                                label="Logo path"
                                hint="Drop your file in public/images and point here."
                                error={form.errors.business_logo}
                                className="sm:col-span-2"
                            >
                                <Input
                                    value={form.data.business_logo}
                                    onChange={(e) => form.setData('business_logo', e.target.value)}
                                    placeholder="/images/logo.png"
                                />
                            </Field>
                        </div>
                    </Card>

                    <Card className="card-pad">
                        <h2 className="font-display text-lg font-semibold text-chocolate-700">
                            Receipts & numbering
                        </h2>
                        <p className="text-sm text-chocolate-400">
                            Receipt numbers reset daily, order numbers yearly. The year is always taken from the
                            clock — never hard-coded.
                        </p>

                        <div className="mt-5 grid gap-4 sm:grid-cols-2">
                            <Field
                                label="Receipt prefix"
                                required
                                hint={`Preview: ${sampleReceipt}`}
                                error={form.errors.receipt_prefix}
                            >
                                <Input
                                    value={form.data.receipt_prefix}
                                    onChange={(e) => form.setData('receipt_prefix', e.target.value.toUpperCase())}
                                    maxLength={6}
                                />
                            </Field>
                            <Field
                                label="Order prefix"
                                required
                                hint={`Preview: ${sampleOrder}`}
                                error={form.errors.order_prefix}
                            >
                                <Input
                                    value={form.data.order_prefix}
                                    onChange={(e) => form.setData('order_prefix', e.target.value.toUpperCase())}
                                    maxLength={6}
                                />
                            </Field>
                            <Field label="Default paper" error={form.errors.receipt_paper}>
                                <Select
                                    value={form.data.receipt_paper}
                                    onChange={(e) => form.setData('receipt_paper', e.target.value)}
                                >
                                    <option value="a4">A4 — full page reseller receipt</option>
                                    <option value="thermal">80mm — thermal slip</option>
                                </Select>
                            </Field>
                            <Field
                                label="Default downpayment %"
                                required
                                error={form.errors.default_downpayment_percent}
                            >
                                <Input
                                    type="number"
                                    min="0"
                                    max="100"
                                    value={form.data.default_downpayment_percent}
                                    onChange={(e) => form.setData('default_downpayment_percent', e.target.value)}
                                />
                            </Field>
                            <Field label="Footer message" error={form.errors.receipt_footer} className="sm:col-span-2">
                                <Input
                                    value={form.data.receipt_footer}
                                    onChange={(e) => form.setData('receipt_footer', e.target.value)}
                                    placeholder="Thank you for growing with Lil'Eu."
                                />
                            </Field>
                            <div className="sm:col-span-2">
                                <Toggle
                                    checked={form.data.receipt_show_logo}
                                    onChange={(v) => form.setData('receipt_show_logo', v)}
                                    label="Show the logo on receipts"
                                    description="Off prints the business name only."
                                />
                            </div>
                        </div>
                    </Card>
                </div>

                {/* Preview + gateway */}
                <div className="space-y-4 xl:sticky xl:top-6">
                    <Card className="overflow-hidden">
                        <div className="bg-chocolate-700 px-5 py-5 text-cream-100">
                            <div className="flex items-center gap-3">
                                {form.data.receipt_show_logo && (
                                    <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-cream-100">
                                        <img src={brand?.logo_mark} alt="" className="h-full w-full object-contain" />
                                    </span>
                                )}
                                <div className="leading-tight">
                                    <p className="font-display text-lg font-semibold">
                                        {form.data.business_name || "Lil'Eu Sweets"}
                                    </p>
                                    <p className="text-[10px] uppercase tracking-[0.18em] text-blush-300">
                                        {form.data.business_tagline}
                                    </p>
                                    <p className="mt-1.5 text-[10px] font-semibold uppercase tracking-[0.2em] text-cream-200/70">
                                        Official Receipt
                                    </p>
                                </div>
                            </div>
                            <p className="mt-3 font-mono text-sm">{sampleReceipt}</p>
                        </div>
                        <div className="bg-cream-100 px-5 py-4 text-center">
                            <p className="font-display text-sm font-semibold text-chocolate-700">
                                {form.data.receipt_footer}
                            </p>
                            <p className="mt-1 text-[11px] text-chocolate-400">
                                {[form.data.business_facebook, form.data.business_phone]
                                    .filter(Boolean)
                                    .join('  ·  ')}
                            </p>
                        </div>
                    </Card>

                    <Card className="card-pad">
                        <h2 className="font-display text-base font-semibold text-chocolate-700">PayMongo QR Ph</h2>

                        {paymongo.configured ? (
                            <p className="mt-3 flex items-start gap-2 rounded-xl bg-success-light px-3 py-2.5 text-sm text-success">
                                <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0" />
                                Live keys are configured. Payments are confirmed by signed webhook only.
                            </p>
                        ) : (
                            <p className="mt-3 flex items-start gap-2 rounded-xl bg-caramel-soft/20 px-3 py-2.5 text-sm text-caramel-dark">
                                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                                Running in demo mode. Add PAYMONGO_SECRET_KEY to your .env to accept real payments.
                            </p>
                        )}

                        <div className="mt-4">
                            <p className="label">Webhook URL</p>
                            <div className="flex items-center gap-2">
                                <code className="min-w-0 flex-1 truncate rounded-xl border border-cream-300 bg-cream-50 px-3 py-2 text-xs text-chocolate-600">
                                    {paymongo.webhook_url}
                                </code>
                                <button
                                    type="button"
                                    onClick={() => navigator.clipboard?.writeText(paymongo.webhook_url)}
                                    className="rounded-lg p-2 text-chocolate-400 transition hover:bg-cream-200 hover:text-chocolate-700"
                                    aria-label="Copy webhook URL"
                                >
                                    <Copy className="h-4 w-4" />
                                </button>
                            </div>
                            <p className="mt-1.5 text-xs text-chocolate-400">
                                Register this in your PayMongo dashboard for the payment.paid event.
                            </p>
                        </div>
                    </Card>

                    <Button type="submit" disabled={form.processing} className="w-full py-3">
                        <Save className="h-4 w-4" /> Save settings
                    </Button>
                </div>
            </form>
        </AdminLayout>
    );
}
