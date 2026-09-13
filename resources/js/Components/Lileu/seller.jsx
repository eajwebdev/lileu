import { Field, Input, Select, Textarea } from '@/Components/Lileu/ui';

/**
 * The blank slate every seller form starts from, so adding and editing one
 * cannot drift apart.
 */
export const BLANK_SELLER = {
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
};

/** Fills a seller form from a record the server already sent us. */
export function sellerFormData(reseller) {
    return {
        name: reseller.name ?? '',
        business_name: reseller.business_name ?? '',
        email: reseller.email ?? '',
        phone: reseller.phone ?? '',
        city: reseller.city ?? '',
        address: reseller.address ?? '',
        engagement: reseller.engagement ?? 'reseller',
        discount_percent: reseller.discount_percent ?? 0,
        downpayment_percent: reseller.downpayment_percent ?? 50,
        admin_notes: reseller.admin_notes ?? '',
    };
}

/**
 * The one set of seller facts, shared by every form that touches one, so a
 * seller is edited in exactly the terms they were created in.
 *
 * Expects a two-column grid around it.
 */
export function SellerFields({ form, loginRequired = false }) {
    const bind = (key) => ({
        value: form.data[key],
        onChange: (e) => form.setData(key, e.target.value),
    });

    return (
        <>
            <Field label="Full name" required error={form.errors.name} className="sm:col-span-2">
                <Input {...bind('name')} />
            </Field>

            <Field label="Business / school" error={form.errors.business_name}>
                <Input {...bind('business_name')} placeholder="Mabinay National High School" />
            </Field>

            <Field label="Mobile number" required error={form.errors.phone}>
                <Input {...bind('phone')} />
            </Field>

            <Field
                label="Email"
                required={loginRequired}
                hint={loginRequired ? 'They sign in with this.' : 'Optional — most consignment sellers have none.'}
                error={form.errors.email}
                className="sm:col-span-2"
            >
                <Input type="email" {...bind('email')} />
            </Field>

            <Field label="City / municipality" error={form.errors.city}>
                <Input {...bind('city')} placeholder="Mabinay" />
            </Field>

            <Field label="Address" error={form.errors.address}>
                <Input {...bind('address')} />
            </Field>

            <Field
                label="How they sell"
                required
                hint="Consignment sellers take stock now and pay after selling."
                error={form.errors.engagement}
                className="sm:col-span-2"
            >
                <Select {...bind('engagement')}>
                    <option value="consignment">Consignment — takes stock, settles later</option>
                    <option value="reseller">Reseller — buys wholesale up front</option>
                    <option value="both">Both</option>
                </Select>
            </Field>

            {/* Wholesale terms mean nothing to a seller who only takes consignment. */}
            {form.data.engagement !== 'consignment' && (
                <>
                    <Field
                        label="Extra discount %"
                        hint="Taken off their order subtotal."
                        error={form.errors.discount_percent}
                    >
                        <Input type="number" min="0" max="50" {...bind('discount_percent')} />
                    </Field>
                    <Field
                        label="Downpayment %"
                        hint="Required before an order is confirmed."
                        error={form.errors.downpayment_percent}
                    >
                        <Input type="number" min="0" max="100" {...bind('downpayment_percent')} />
                    </Field>
                </>
            )}

            <Field label="Internal notes" error={form.errors.admin_notes} className="sm:col-span-2">
                <Textarea rows={2} {...bind('admin_notes')} placeholder="Only staff see this." />
            </Field>
        </>
    );
}
