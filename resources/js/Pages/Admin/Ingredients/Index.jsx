import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Carrot, Pencil, Plus, Search, ShoppingBasket, Trash2 } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import {
    Badge,
    Button,
    ButtonLink,
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

const BLANK = {
    name: '',
    unit: 'pc',
    category: 'ingredient',
    supplier: '',
    last_price: '',
    notes: '',
    is_active: true,
};

/**
 * The reusable catalog. Everything here is entered once and then picked from
 * the list on every purchase, so this page is mostly about keeping names,
 * units and prices tidy — the prices themselves update as purchases are saved.
 */
export default function Index({ ingredients, units, categories, filters }) {
    const [term, setTerm] = useState(filters.q ?? '');
    const [editing, setEditing] = useState(null);

    const form = useForm(BLANK);

    const open = (ingredient = null) => {
        form.clearErrors();
        form.setDefaults(BLANK);
        form.setData(
            ingredient
                ? {
                      name: ingredient.name,
                      unit: ingredient.unit,
                      category: ingredient.category,
                      supplier: ingredient.supplier ?? '',
                      last_price: String(ingredient.last_price ?? ''),
                      notes: ingredient.notes ?? '',
                      is_active: ingredient.is_active,
                  }
                : BLANK,
        );
        setEditing(ingredient ?? 'new');
    };

    const close = () => {
        setEditing(null);
        form.reset();
        form.clearErrors();
    };

    const submit = (e) => {
        e.preventDefault();

        const options = { preserveScroll: true, onSuccess: close };

        if (editing === 'new') form.post(route('admin.ingredients.store'), options);
        else form.put(route('admin.ingredients.update', editing.id), options);
    };

    const filter = (params) =>
        router.get(route('admin.ingredients.index'), { q: term, ...filters, ...params }, {
            preserveState: true,
            replace: true,
        });

    return (
        <AdminLayout
            title="Ingredients"
            subtitle="Add a thing once; every purchase after that is a pick from this list."
            action={
                <div className="flex items-center gap-2">
                    <ButtonLink href={route('admin.purchases.create')} variant="secondary">
                        <ShoppingBasket className="h-4 w-4" /> Record purchase
                    </ButtonLink>
                    <Button onClick={() => open()}>
                        <Plus className="h-4 w-4" /> New ingredient
                    </Button>
                </div>
            }
        >
            <div className="space-y-4">
                <Card className="card-pad">
                    <div className="flex flex-wrap items-center gap-2.5">
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                filter({});
                            }}
                            className="relative min-w-0 flex-1 sm:max-w-sm"
                        >
                            <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-chocolate-300" />
                            <Input
                                value={term}
                                onChange={(e) => setTerm(e.target.value)}
                                placeholder="Search name or supplier…"
                                className="pl-10"
                            />
                        </form>

                        <Select
                            value={filters.category ?? ''}
                            onChange={(e) => filter({ category: e.target.value })}
                            className="w-auto"
                        >
                            <option value="">All categories</option>
                            {categories.map((c) => (
                                <option key={c} value={c} className="capitalize">
                                    {c}
                                </option>
                            ))}
                        </Select>
                    </div>
                </Card>

                {ingredients.data.length === 0 ? (
                    <EmptyState
                        icon={Carrot}
                        title="No ingredients yet"
                        description="Add the things you buy regularly — flour, cream, cups — and they'll be one tap away on every purchase."
                        action={
                            <Button onClick={() => open()}>
                                <Plus className="h-4 w-4" /> New ingredient
                            </Button>
                        }
                    />
                ) : (
                    <Card className="card-pad">
                        <div className="overflow-x-auto">
                            <table className="table-lileu min-w-full">
                                <thead>
                                    <tr>
                                        <th>Ingredient</th>
                                        <th>Supplier</th>
                                        <th className="text-right">Last price</th>
                                        <th className="text-right">Bought</th>
                                        <th className="w-20" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {ingredients.data.map((ingredient) => (
                                        <tr key={ingredient.id}>
                                            <td>
                                                <div className="flex items-center gap-2">
                                                    <p className="font-medium text-chocolate-700">{ingredient.name}</p>
                                                    {!ingredient.is_active && <Badge tone="muted-red">Archived</Badge>}
                                                </div>
                                                <p className="text-[11px] capitalize text-chocolate-300">
                                                    per {ingredient.unit} · {ingredient.category}
                                                </p>
                                            </td>
                                            <td className="text-sm text-chocolate-500">{ingredient.supplier || '—'}</td>
                                            <td className="text-right">
                                                <p className="font-semibold tabular-nums text-chocolate-700">
                                                    <Money value={ingredient.last_price} />
                                                </p>
                                                <p className="text-[11px] text-chocolate-300">
                                                    {ingredient.last_purchased_on ?? 'never bought'}
                                                </p>
                                            </td>
                                            <td className="text-right text-sm tabular-nums text-chocolate-500">
                                                {ingredient.times_bought}×
                                            </td>
                                            <td>
                                                <div className="flex justify-end gap-1">
                                                    <button
                                                        type="button"
                                                        onClick={() => open(ingredient)}
                                                        className="rounded-lg p-1.5 text-chocolate-400 transition hover:bg-cream-200 hover:text-chocolate-700"
                                                        aria-label={`Edit ${ingredient.name}`}
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            if (!confirm(`Remove ${ingredient.name}?`)) return;
                                                            router.delete(
                                                                route('admin.ingredients.destroy', ingredient.id),
                                                                { preserveScroll: true },
                                                            );
                                                        }}
                                                        className="rounded-lg p-1.5 text-chocolate-400 transition hover:bg-cherry/10 hover:text-cherry"
                                                        aria-label={`Remove ${ingredient.name}`}
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
                    </Card>
                )}

                <Pagination links={ingredients.links} />
            </div>

            <Modal
                open={editing !== null}
                onClose={close}
                title={editing === 'new' ? 'New ingredient' : 'Edit ingredient'}
                description="The price here is the starting point on the next purchase — you can still change it there."
                size="sm"
                footer={
                    <>
                        <Button type="button" variant="secondary" onClick={close}>
                            Cancel
                        </Button>
                        <Button type="submit" form="ingredient-form" disabled={form.processing}>
                            {editing === 'new' ? 'Add ingredient' : 'Save changes'}
                        </Button>
                    </>
                }
            >
                <form id="ingredient-form" onSubmit={submit} className="grid gap-3 sm:grid-cols-2">
                    <Field label="Name" error={form.errors.name} required className="sm:col-span-2">
                        <Input
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder="All-purpose flour"
                        />
                    </Field>
                    <Field label="Unit" error={form.errors.unit} required>
                        <Select value={form.data.unit} onChange={(e) => form.setData('unit', e.target.value)}>
                            {units.map((u) => (
                                <option key={u} value={u}>
                                    {u}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Category" error={form.errors.category} required>
                        <Select value={form.data.category} onChange={(e) => form.setData('category', e.target.value)}>
                            {categories.map((c) => (
                                <option key={c} value={c} className="capitalize">
                                    {c}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Supplier" error={form.errors.supplier}>
                        <Input
                            value={form.data.supplier}
                            onChange={(e) => form.setData('supplier', e.target.value)}
                            placeholder="Graham House"
                        />
                    </Field>
                    <Field label="Price per unit" error={form.errors.last_price} required>
                        <Input
                            type="number"
                            step="0.01"
                            min="0"
                            value={form.data.last_price}
                            onChange={(e) => form.setData('last_price', e.target.value)}
                            placeholder="0.00"
                        />
                    </Field>
                    <Field label="Notes" error={form.errors.notes} className="sm:col-span-2">
                        <Textarea
                            rows={2}
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                            placeholder="Brand, pack size, anything worth remembering."
                        />
                    </Field>
                    <div className="sm:col-span-2">
                        <Toggle
                            checked={form.data.is_active}
                            onChange={(v) => form.setData('is_active', v)}
                            label="Active"
                            description="Archived ingredients stay in past purchases but drop off the picker."
                        />
                    </div>
                </form>
            </Modal>
        </AdminLayout>
    );
}
