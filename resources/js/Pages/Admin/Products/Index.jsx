import { router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import clsx from 'clsx';
import { ChevronDown, ChevronRight, IceCreamCone, Package, Pencil, Plus, Search, Tags, Trash2 } from 'lucide-react';
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
    Select,
    Textarea,
    Toggle,
} from '@/Components/Lileu/ui';
import { ProductImage } from '@/Components/Lileu/product';

const BLANK = {
    name: '',
    sku: '',
    category_id: '',
    description: '',
    image_path: '',
    retail_price: '',
    reseller_price: '',
    cost_price: '',
    stock: 0,
    tracks_stock: true,
    low_stock_threshold: 10,
    min_reseller_qty: 10,
    is_active: true,
    is_available: true,
    is_featured: false,
    available_to_resellers: true,
    sort_order: 0,
};

const ACCENTS = ['blush', 'caramel', 'cherry', 'chocolate', 'success'];

const BLANK_VARIANT = {
    name: '',
    sku: '',
    description: '',
    image_path: '',
    retail_price: '',
    reseller_price: '',
    cost_price: '',
    stock: 0,
    tracks_stock: true,
    low_stock_threshold: 10,
    is_active: true,
    is_available: true,
    sort_order: 0,
};

export default function Index({ products, categories, filters }) {
    const [term, setTerm] = useState(filters.q ?? '');
    const [editing, setEditing] = useState(null);
    const [showCategories, setShowCategories] = useState(false);
    const [expanded, setExpanded] = useState({});
    // { product, variant } — variant null means a new flavour.
    const [variantEditing, setVariantEditing] = useState(null);

    const form = useForm(BLANK);

    const openCreate = () => {
        form.setDefaults(BLANK);
        form.reset();
        form.clearErrors();
        setEditing('new');
    };

    const openEdit = (product) => {
        form.setData({
            name: product.name,
            sku: product.sku,
            category_id: product.category_id ?? '',
            description: product.description ?? '',
            image_path: product.image_path ?? '',
            retail_price: product.retail_price,
            reseller_price: product.reseller_price,
            cost_price: product.cost_price,
            stock: product.stock,
            tracks_stock: product.tracks_stock,
            low_stock_threshold: product.low_stock_threshold,
            min_reseller_qty: product.min_reseller_qty,
            is_active: product.is_active,
            is_available: product.is_available,
            is_featured: product.is_featured,
            available_to_resellers: product.available_to_resellers,
            sort_order: 0,
        });
        form.clearErrors();
        setEditing(product);
    };

    const submit = (e) => {
        e.preventDefault();

        const onSuccess = () => setEditing(null);

        if (editing === 'new') {
            form.post(route('admin.products.store'), { preserveScroll: true, onSuccess });
        } else {
            form.put(route('admin.products.update', editing.id), { preserveScroll: true, onSuccess });
        }
    };

    return (
        <AdminLayout
            title="Products"
            subtitle="Pricing, stock and what resellers are allowed to order."
            action={
                <div className="flex flex-wrap gap-2">
                    <Button variant="ghost" onClick={() => setShowCategories(true)}>
                        <Tags className="h-4 w-4" /> Categories
                    </Button>
                    <Button onClick={openCreate}>
                        <Plus className="h-4 w-4" /> New product
                    </Button>
                </div>
            }
        >
            <Card className="card-pad">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get(route('admin.products.index'), { q: term || undefined }, { preserveState: true });
                    }}
                    className="mb-5 max-w-sm"
                >
                    <div className="relative">
                        <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-chocolate-300" />
                        <Input
                            value={term}
                            onChange={(e) => setTerm(e.target.value)}
                            placeholder="Search name or SKU…"
                            className="pl-10"
                        />
                    </div>
                </form>

                {products.data.length === 0 ? (
                    <EmptyState
                        icon={Package}
                        title="No products yet"
                        description="Add your first dessert to start selling at the counter and through resellers."
                        action={<Button onClick={openCreate}>Add a product</Button>}
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="table-lileu min-w-full">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th className="text-right">Retail</th>
                                    <th className="text-right">Reseller</th>
                                    <th className="text-right">Cost</th>
                                    <th className="text-right">Stock</th>
                                    <th>Visibility</th>
                                    <th className="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {products.data.map((product) => (
                                    <tr key={product.id} className="transition hover:bg-cream-50">
                                        <td>
                                            <div className="flex items-center gap-3">
                                                <div className="h-11 w-11 shrink-0 overflow-hidden rounded-xl bg-cream-200">
                                                    <ProductImage
                                                        product={product}
                                                        accent={product.category?.accent}
                                                    />
                                                </div>
                                                <div className="min-w-0">
                                                    <p className="truncate font-medium text-chocolate-700">
                                                        {product.name}
                                                    </p>
                                                    <p className="font-mono text-[11px] text-chocolate-300">
                                                        {product.sku}
                                                    </p>
                                                    {product.has_variants && (
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                setExpanded((prev) => ({
                                                                    ...prev,
                                                                    [product.id]: !prev[product.id],
                                                                }))
                                                            }
                                                            className="mt-1 inline-flex items-center gap-1 text-[11px] font-medium text-blush-600 transition hover:text-chocolate-700"
                                                        >
                                                            {expanded[product.id] ? (
                                                                <ChevronDown className="h-3 w-3" />
                                                            ) : (
                                                                <ChevronRight className="h-3 w-3" />
                                                            )}
                                                            {product.variants.length}{' '}
                                                            {product.variants.length === 1 ? 'flavour' : 'flavours'}
                                                        </button>
                                                    )}
                                                </div>
                                            </div>
                                        </td>
                                        <td className="text-xs">{product.category?.name ?? '—'}</td>
                                        <td className="text-right font-semibold tabular-nums text-chocolate-700">
                                            {product.has_variants ? (
                                                product.from_price === product.to_price ? (
                                                    <Money value={product.from_price} />
                                                ) : (
                                                    <span className="text-xs">
                                                        <Money value={product.from_price} /> –{' '}
                                                        <Money value={product.to_price} />
                                                    </span>
                                                )
                                            ) : (
                                                <Money value={product.retail_price} />
                                            )}
                                        </td>
                                        <td className="text-right tabular-nums">
                                            {product.has_variants ? (
                                                <span className="text-chocolate-300">by flavour</span>
                                            ) : (
                                                <Money value={product.reseller_price} />
                                            )}
                                        </td>
                                        <td className="text-right tabular-nums text-chocolate-400">
                                            {product.has_variants ? '—' : <Money value={product.cost_price} />}
                                        </td>
                                        <td className="text-right">
                                            {product.has_variants ? (
                                                <span
                                                    className={clsx(
                                                        'badge',
                                                        product.is_low_stock
                                                            ? 'bg-caramel-soft/30 text-caramel-dark'
                                                            : 'bg-success-light text-success',
                                                    )}
                                                >
                                                    {product.total_stock}
                                                </span>
                                            ) : product.tracks_stock ? (
                                                <span
                                                    className={clsx(
                                                        'badge',
                                                        product.is_low_stock
                                                            ? 'bg-caramel-soft/30 text-caramel-dark'
                                                            : 'bg-success-light text-success',
                                                    )}
                                                >
                                                    {product.stock}
                                                </span>
                                            ) : (
                                                <Badge tone="blush">Made to order</Badge>
                                            )}
                                        </td>
                                        <td>
                                            <div className="flex flex-wrap gap-1">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        router.patch(
                                                            route('admin.products.availability', product.id),
                                                            { is_available: !product.is_available },
                                                            { preserveScroll: true },
                                                        )
                                                    }
                                                    aria-pressed={product.is_available}
                                                    aria-label={`${product.is_available ? 'Mark' : 'Make'} ${product.name} ${product.is_available ? 'unavailable' : 'available'}`}
                                                    className={clsx(
                                                        'badge cursor-pointer border transition active:scale-95',
                                                        product.is_available
                                                            ? 'border-success/20 bg-success-light text-success hover:bg-success/15'
                                                            : 'border-cherry/20 bg-cherry/10 text-cherry-dark hover:bg-cherry/15',
                                                    )}
                                                >
                                                    <span
                                                        className={clsx(
                                                            'h-1.5 w-1.5 rounded-full',
                                                            product.is_available ? 'bg-success' : 'bg-cherry',
                                                        )}
                                                    />
                                                    {product.is_available ? 'Available' : 'Unavailable'}
                                                </button>
                                                {!product.is_active && <Badge tone="muted-red">Hidden</Badge>}
                                                {product.is_featured && <Badge tone="cherry">Featured</Badge>}
                                                {product.available_to_resellers && (
                                                    <Badge tone="blush">Reseller</Badge>
                                                )}
                                            </div>
                                        </td>
                                        <td>
                                            <div className="flex justify-end gap-1">
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setExpanded((prev) => ({ ...prev, [product.id]: true }));
                                                        setVariantEditing({ product, variant: null });
                                                    }}
                                                    className="rounded-lg p-1.5 text-chocolate-400 transition hover:bg-blush-100 hover:text-blush-600"
                                                    aria-label={`Add a flavour to ${product.name}`}
                                                >
                                                    <IceCreamCone className="h-4 w-4" />
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => openEdit(product)}
                                                    className="rounded-lg p-1.5 text-chocolate-400 transition hover:bg-cream-200 hover:text-chocolate-700"
                                                    aria-label={`Edit ${product.name}`}
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        if (window.confirm(`Remove ${product.name}?`)) {
                                                            router.delete(
                                                                route('admin.products.destroy', product.id),
                                                                { preserveScroll: true },
                                                            );
                                                        }
                                                    }}
                                                    className="rounded-lg p-1.5 text-chocolate-400 transition hover:bg-cherry/10 hover:text-cherry"
                                                    aria-label={`Delete ${product.name}`}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                )).flatMap((row, i) => {
                                    const product = products.data[i];

                                    if (!product.has_variants || !expanded[product.id]) return [row];

                                    return [
                                        row,
                                        <tr key={`${product.id}-variants`} className="bg-cream-50/70">
                                            <td colSpan={8} className="px-4 py-3">
                                                <VariantRows
                                                    product={product}
                                                    onEdit={(variant) =>
                                                        setVariantEditing({ product, variant })
                                                    }
                                                />
                                            </td>
                                        </tr>,
                                    ];
                                })}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination links={products.links} className="mt-6" />
            </Card>

            {/* Product form */}
            <Modal
                open={editing !== null}
                onClose={() => setEditing(null)}
                title={editing === 'new' ? 'New product' : `Edit ${editing?.name ?? ''}`}
                description="Retail is what walk-in customers pay; reseller is the wholesale rate."
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setEditing(null)}>
                            Cancel
                        </Button>
                        <Button onClick={submit} disabled={form.processing}>
                            {editing === 'new' ? 'Add product' : 'Save changes'}
                        </Button>
                    </>
                }
            >
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <Field label="Name" required error={form.errors.name} className="sm:col-span-2">
                        <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    </Field>

                    <Field label="SKU" required error={form.errors.sku}>
                        <Input value={form.data.sku} onChange={(e) => form.setData('sku', e.target.value)} />
                    </Field>

                    <Field label="Category" error={form.errors.category_id}>
                        <Select
                            value={form.data.category_id}
                            onChange={(e) => form.setData('category_id', e.target.value)}
                        >
                            <option value="">No category</option>
                            {categories.map((category) => (
                                <option key={category.id} value={category.id}>
                                    {category.name}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Field label="Description" error={form.errors.description} className="sm:col-span-2">
                        <Textarea
                            rows={2}
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                        />
                    </Field>

                    <Field
                        label="Image path or URL"
                        hint="e.g. /images/products/graham-nest.jpg"
                        error={form.errors.image_path}
                        className="sm:col-span-2"
                    >
                        <Input
                            value={form.data.image_path}
                            onChange={(e) => form.setData('image_path', e.target.value)}
                        />
                    </Field>

                    <Field label="Retail price" required error={form.errors.retail_price}>
                        <Input
                            type="number"
                            step="0.01"
                            value={form.data.retail_price}
                            onChange={(e) => form.setData('retail_price', e.target.value)}
                        />
                    </Field>

                    <Field label="Reseller price" required error={form.errors.reseller_price}>
                        <Input
                            type="number"
                            step="0.01"
                            value={form.data.reseller_price}
                            onChange={(e) => form.setData('reseller_price', e.target.value)}
                        />
                    </Field>

                    <Field label="Cost price" error={form.errors.cost_price}>
                        <Input
                            type="number"
                            step="0.01"
                            value={form.data.cost_price}
                            onChange={(e) => form.setData('cost_price', e.target.value)}
                        />
                    </Field>

                    <Field
                        label="Inventory mode"
                        hint="Made-to-order products stay sellable even with zero stock."
                        error={form.errors.tracks_stock}
                        className="sm:col-span-2"
                    >
                        <Select
                            value={form.data.tracks_stock ? 'stocked' : 'made_to_order'}
                            onChange={(e) => form.setData('tracks_stock', e.target.value === 'stocked')}
                        >
                            <option value="stocked">Stocked — count and deduct inventory</option>
                            <option value="made_to_order">Made to order — do not count stock</option>
                        </Select>
                    </Field>

                    {form.data.tracks_stock && (
                        <>
                            <Field label="Stock on hand" required error={form.errors.stock}>
                                <Input
                                    type="number"
                                    value={form.data.stock}
                                    onChange={(e) => form.setData('stock', e.target.value)}
                                />
                            </Field>

                            <Field label="Low stock alert at" required error={form.errors.low_stock_threshold}>
                                <Input
                                    type="number"
                                    value={form.data.low_stock_threshold}
                                    onChange={(e) => form.setData('low_stock_threshold', e.target.value)}
                                />
                            </Field>
                        </>
                    )}

                    <Field label="Minimum reseller qty" required error={form.errors.min_reseller_qty}>
                        <Input
                            type="number"
                            value={form.data.min_reseller_qty}
                            onChange={(e) => form.setData('min_reseller_qty', e.target.value)}
                        />
                    </Field>

                    <div className="space-y-3 sm:col-span-2">
                        <Toggle
                            checked={form.data.is_active}
                            onChange={(v) => form.setData('is_active', v)}
                            label="Active"
                            description="Visible on the public menu and at the counter."
                        />
                        <Toggle
                            checked={form.data.is_available}
                            onChange={(v) => form.setData('is_available', v)}
                            label="Available for sale"
                            description="Turn off to keep it visible but mark it unavailable everywhere."
                        />
                        <Toggle
                            checked={form.data.is_featured}
                            onChange={(v) => form.setData('is_featured', v)}
                            label="Featured"
                            description="Highlighted on the landing page as a bestseller."
                        />
                        <Toggle
                            checked={form.data.available_to_resellers}
                            onChange={(v) => form.setData('available_to_resellers', v)}
                            label="Available to resellers"
                            description="Included in the default wholesale catalog."
                        />
                    </div>
                </form>
            </Modal>

            <CategoryModal open={showCategories} onClose={() => setShowCategories(false)} categories={categories} />

            <VariantModal
                open={variantEditing !== null}
                product={variantEditing?.product}
                variant={variantEditing?.variant}
                onClose={() => setVariantEditing(null)}
            />
        </AdminLayout>
    );
}

function CategoryModal({ open, onClose, categories }) {
    const form = useForm({ name: '', description: '', accent: 'blush', sort_order: 0, is_active: true });

    return (
        <Modal open={open} onClose={onClose} title="Categories" description="Shelves used across the menu and POS.">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(route('admin.categories.store'), {
                        preserveScroll: true,
                        onSuccess: () => form.reset(),
                    });
                }}
                className="grid gap-3 rounded-2xl border border-blush-200 bg-blush-50 p-4 sm:grid-cols-[1.4fr_1fr_auto]"
            >
                <Field label="Name" error={form.errors.name}>
                    <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                </Field>
                <Field label="Accent" error={form.errors.accent}>
                    <Select value={form.data.accent} onChange={(e) => form.setData('accent', e.target.value)}>
                        {ACCENTS.map((accent) => (
                            <option key={accent} value={accent} className="capitalize">
                                {accent}
                            </option>
                        ))}
                    </Select>
                </Field>
                <div className="flex items-end">
                    <Button type="submit" disabled={form.processing}>
                        <Plus className="h-4 w-4" /> Add
                    </Button>
                </div>
            </form>

            <ul className="mt-4 divide-y divide-cream-200">
                {categories.map((category) => (
                    <li key={category.id} className="flex items-center justify-between gap-3 py-3">
                        <div>
                            <p className="font-medium text-chocolate-700">{category.name}</p>
                            <p className="text-xs text-chocolate-400">{category.description}</p>
                        </div>
                        <div className="flex items-center gap-2">
                            <Badge tone={category.accent}>{category.accent}</Badge>
                            <button
                                type="button"
                                onClick={() => {
                                    if (window.confirm(`Remove the ${category.name} category?`)) {
                                        router.delete(route('admin.categories.destroy', category.id), {
                                            preserveScroll: true,
                                        });
                                    }
                                }}
                                className="rounded-lg p-1.5 text-chocolate-400 transition hover:bg-cherry/10 hover:text-cherry"
                                aria-label={`Delete ${category.name}`}
                            >
                                <Trash2 className="h-4 w-4" />
                            </button>
                        </div>
                    </li>
                ))}
            </ul>
        </Modal>
    );
}

/**
 * The flavours of one product, each with the price and stock it sells at.
 */
function VariantRows({ product, onEdit }) {
    return (
        <div className="overflow-x-auto rounded-xl border border-cream-200 bg-vanilla">
            <table className="table-lileu min-w-full">
                <thead>
                    <tr>
                        <th>Flavour</th>
                        <th className="text-right">Retail</th>
                        <th className="text-right">Reseller</th>
                        <th className="text-right">Cost</th>
                        <th className="text-right">Stock</th>
                        <th>Visibility</th>
                        <th className="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {product.variants.map((variant) => (
                        <tr key={variant.id}>
                            <td>
                                <p className="font-medium text-chocolate-700">{variant.name}</p>
                                <p className="font-mono text-[11px] text-chocolate-300">{variant.sku}</p>
                            </td>
                            <td className="text-right font-semibold tabular-nums text-chocolate-700">
                                <Money value={variant.retail_price} />
                            </td>
                            <td className="text-right tabular-nums">
                                <Money value={variant.reseller_price} />
                            </td>
                            <td className="text-right tabular-nums text-chocolate-400">
                                <Money value={variant.cost_price} />
                            </td>
                            <td className="text-right">
                                {variant.tracks_stock ? (
                                    <span
                                        className={clsx(
                                            'badge',
                                            variant.is_low_stock
                                                ? 'bg-caramel-soft/30 text-caramel-dark'
                                                : 'bg-success-light text-success',
                                        )}
                                    >
                                        {variant.stock}
                                    </span>
                                ) : (
                                    <Badge tone="blush">Made to order</Badge>
                                )}
                            </td>
                            <td>
                                <div className="flex flex-wrap gap-1">
                                    <button
                                        type="button"
                                        onClick={() =>
                                            router.patch(
                                                route('admin.products.variants.availability', [
                                                    product.id,
                                                    variant.id,
                                                ]),
                                                { is_available: !variant.is_available },
                                                { preserveScroll: true },
                                            )
                                        }
                                        aria-pressed={variant.is_available}
                                        className={clsx(
                                            'badge cursor-pointer border transition active:scale-95',
                                            variant.is_available
                                                ? 'border-success/20 bg-success-light text-success hover:bg-success/15'
                                                : 'border-cherry/20 bg-cherry/10 text-cherry-dark hover:bg-cherry/15',
                                        )}
                                    >
                                        <span
                                            className={clsx(
                                                'h-1.5 w-1.5 rounded-full',
                                                variant.is_available ? 'bg-success' : 'bg-cherry',
                                            )}
                                        />
                                        {variant.is_available ? 'Available' : 'Unavailable'}
                                    </button>
                                    {!variant.is_active && <Badge tone="muted-red">Hidden</Badge>}
                                </div>
                            </td>
                            <td>
                                <div className="flex justify-end gap-1">
                                    <button
                                        type="button"
                                        onClick={() => onEdit(variant)}
                                        className="rounded-lg p-1.5 text-chocolate-400 transition hover:bg-cream-200 hover:text-chocolate-700"
                                        aria-label={`Edit ${variant.name}`}
                                    >
                                        <Pencil className="h-4 w-4" />
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            if (window.confirm(`Remove ${variant.name}?`)) {
                                                router.delete(
                                                    route('admin.products.variants.destroy', [
                                                        product.id,
                                                        variant.id,
                                                    ]),
                                                    { preserveScroll: true },
                                                );
                                            }
                                        }}
                                        className="rounded-lg p-1.5 text-chocolate-400 transition hover:bg-cherry/10 hover:text-cherry"
                                        aria-label={`Delete ${variant.name}`}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function VariantModal({ open, product, variant, onClose }) {
    const form = useForm(BLANK_VARIANT);

    // Load the flavour being edited each time the modal opens for one.
    useEffect(() => {
        if (!open) return;

        form.clearErrors();
        form.setData(
            variant
                ? {
                      name: variant.name,
                      sku: variant.sku,
                      description: variant.description ?? '',
                      image_path: variant.image_path ?? '',
                      retail_price: variant.retail_price,
                      reseller_price: variant.reseller_price,
                      cost_price: variant.cost_price,
                      stock: variant.stock,
                      tracks_stock: variant.tracks_stock,
                      low_stock_threshold: variant.low_stock_threshold,
                      is_active: variant.is_active,
                      is_available: variant.is_available,
                      sort_order: variant.sort_order,
                  }
                : BLANK_VARIANT,
        );
    }, [open, variant?.id]);

    if (!product) return null;

    const submit = (e) => {
        e.preventDefault();

        const options = { preserveScroll: true, onSuccess: onClose };

        if (variant) form.put(route('admin.products.variants.update', [product.id, variant.id]), options);
        else form.post(route('admin.products.variants.store', product.id), options);
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={variant ? `Edit ${variant.name}` : `New flavour of ${product.name}`}
            description="A flavour carries its own price and its own stock."
            footer={
                <>
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" form="variant-form" disabled={form.processing}>
                        {variant ? 'Save changes' : 'Add flavour'}
                    </Button>
                </>
            }
        >
            <form id="variant-form" onSubmit={submit} className="grid gap-3 sm:grid-cols-2">
                <Field label="Name" error={form.errors.name} required>
                    <Input
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        placeholder="Cloudy Classic"
                    />
                </Field>
                <Field label="SKU" error={form.errors.sku} required>
                    <Input
                        value={form.data.sku}
                        onChange={(e) => form.setData('sku', e.target.value)}
                        placeholder="GN-CL"
                    />
                </Field>
                <Field label="Description" error={form.errors.description} className="sm:col-span-2">
                    <Textarea
                        rows={2}
                        value={form.data.description}
                        onChange={(e) => form.setData('description', e.target.value)}
                    />
                </Field>
                <Field label="Retail price" error={form.errors.retail_price} required>
                    <Input
                        type="number"
                        step="0.01"
                        min="0"
                        value={form.data.retail_price}
                        onChange={(e) => form.setData('retail_price', e.target.value)}
                    />
                </Field>
                <Field label="Reseller price" error={form.errors.reseller_price} required>
                    <Input
                        type="number"
                        step="0.01"
                        min="0"
                        value={form.data.reseller_price}
                        onChange={(e) => form.setData('reseller_price', e.target.value)}
                    />
                </Field>
                <Field label="Cost price" error={form.errors.cost_price}>
                    <Input
                        type="number"
                        step="0.01"
                        min="0"
                        value={form.data.cost_price}
                        onChange={(e) => form.setData('cost_price', e.target.value)}
                    />
                </Field>
                <Field label="Stock" error={form.errors.stock} required>
                    <Input
                        type="number"
                        min="0"
                        value={form.data.stock}
                        onChange={(e) => form.setData('stock', e.target.value)}
                        disabled={!form.data.tracks_stock}
                    />
                </Field>
                <Field label="Low stock alert" error={form.errors.low_stock_threshold}>
                    <Input
                        type="number"
                        min="0"
                        value={form.data.low_stock_threshold}
                        onChange={(e) => form.setData('low_stock_threshold', e.target.value)}
                    />
                </Field>
                <Field label="Sort order" error={form.errors.sort_order}>
                    <Input
                        type="number"
                        min="0"
                        value={form.data.sort_order}
                        onChange={(e) => form.setData('sort_order', e.target.value)}
                    />
                </Field>
                <div className="space-y-2.5 sm:col-span-2">
                    <Toggle
                        checked={form.data.tracks_stock}
                        onChange={(v) => form.setData('tracks_stock', v)}
                        label="Track stock"
                        description="Off means made to order, so it never runs out."
                    />
                    <Toggle
                        checked={form.data.is_available}
                        onChange={(v) => form.setData('is_available', v)}
                        label="Available"
                        description="Switch off to pull it from the counter without hiding it."
                    />
                    <Toggle
                        checked={form.data.is_active}
                        onChange={(v) => form.setData('is_active', v)}
                        label="Active"
                        description="Inactive flavours drop off every list, including the storefront."
                    />
                </div>
            </form>
        </Modal>
    );
}
