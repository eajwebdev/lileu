import { Link, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import clsx from 'clsx';
import { ArrowLeft, Package, Plus, Search, ShoppingBasket, Sparkles, Trash2 } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Button, Card, EmptyState, Field, Input, Money, Select, Textarea, peso } from '@/Components/Lileu/ui';

/**
 * Buying ingredients.
 *
 * The left panel is the reusable catalog — an ingredient entered once is
 * picked from here forever after, and arrives carrying the price last paid.
 * The basket lets that price be corrected before saving; whatever it ends at
 * becomes the ingredient's new default.
 */
export default function Create({ ingredients, units, categories, suppliers }) {
    const [term, setTerm] = useState('');
    const [lines, setLines] = useState([]);
    const [draft, setDraft] = useState(null);

    const { data, setData, post, processing, errors, transform } = useForm({
        purchased_on: new Date().toISOString().slice(0, 10),
        supplier: '',
        reference: '',
        description: '',
        notes: '',
        items: [],
    });

    const search = term.trim().toLowerCase();

    const visible = useMemo(
        () =>
            ingredients.filter(
                (i) =>
                    !search ||
                    i.name.toLowerCase().includes(search) ||
                    (i.supplier ?? '').toLowerCase().includes(search),
            ),
        [ingredients, search],
    );

    const exactMatch = ingredients.some((i) => i.name.toLowerCase() === search);
    const inBasket = (id) => lines.some((l) => l.ingredient_id === id);

    /** Adding from the catalog: the last price paid rides along as the default. */
    const addExisting = (ingredient) => {
        if (inBasket(ingredient.id)) return;

        setLines((prev) => [
            ...prev,
            {
                key: `i-${ingredient.id}`,
                ingredient_id: ingredient.id,
                name: ingredient.name,
                unit: ingredient.unit,
                catalog_price: ingredient.last_price,
                quantity: '1',
                unit_price: String(ingredient.last_price ?? 0),
            },
        ]);
        setTerm('');
    };

    /** A name not in the catalog yet — saving the purchase also files it away. */
    const addDraft = () => {
        if (!draft?.name.trim()) return;

        setLines((prev) => [
            ...prev,
            {
                key: `n-${Date.now()}`,
                ingredient_id: null,
                name: draft.name.trim(),
                unit: draft.unit,
                category: draft.category,
                catalog_price: null,
                quantity: '1',
                unit_price: draft.unit_price || '0',
            },
        ]);
        setDraft(null);
        setTerm('');
    };

    const updateLine = (key, patch) =>
        setLines((prev) => prev.map((l) => (l.key === key ? { ...l, ...patch } : l)));

    const removeLine = (key) => setLines((prev) => prev.filter((l) => l.key !== key));

    const lineTotal = (line) => (Number(line.quantity) || 0) * (Number(line.unit_price) || 0);
    const total = lines.reduce((sum, l) => sum + lineTotal(l), 0);

    /** A corrected price is worth showing: it is what will move the catalog. */
    const priceMoved = (line) =>
        line.catalog_price !== null &&
        Number(line.catalog_price) !== (Number(line.unit_price) || 0);

    const submit = (e) => {
        e.preventDefault();

        transform((form) => ({
            ...form,
            items: lines.map((l) => ({
                ingredient_id: l.ingredient_id,
                name: l.ingredient_id ? null : l.name,
                unit: l.unit,
                category: l.category,
                quantity: Number(l.quantity) || 0,
                unit_price: Number(l.unit_price) || 0,
            })),
        }));

        post(route('admin.purchases.store'));
    };

    return (
        <AdminLayout
            title="Record a purchase"
            subtitle="Pick what you bought, correct the price if it moved, and save."
            action={
                <Link
                    href={route('admin.purchases.index')}
                    className="inline-flex items-center gap-1.5 text-sm font-medium text-chocolate-400 transition hover:text-chocolate-700"
                >
                    <ArrowLeft className="h-4 w-4" /> All purchases
                </Link>
            }
        >
            <form onSubmit={submit} className="grid gap-4 lg:grid-cols-[22rem_minmax(0,1fr)] xl:grid-cols-[24rem_minmax(0,1fr)]">
                {/* Catalog — the reusable side */}
                <section className="space-y-3">
                    <Card className="card-pad">
                        <div className="mb-3 flex items-center gap-2.5">
                            <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-blush-100 text-blush-600">
                                <Package className="h-4.5 w-4.5" />
                            </span>
                            <div>
                                <p className="text-sm font-semibold text-chocolate-700">Ingredient list</p>
                                <p className="text-[11px] text-chocolate-400">Tap to add. Prices prefill.</p>
                            </div>
                        </div>

                        <div className="relative">
                            <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-chocolate-300" />
                            <Input
                                value={term}
                                onChange={(e) => setTerm(e.target.value)}
                                placeholder="Search or type a new name…"
                                className="pl-10"
                            />
                        </div>

                        {/* Anything not in the catalog can be filed on the spot. */}
                        {search && !exactMatch && !draft && (
                            <button
                                type="button"
                                onClick={() =>
                                    setDraft({ name: term.trim(), unit: 'pc', category: 'ingredient', unit_price: '' })
                                }
                                className="mt-2.5 flex w-full items-center gap-2 rounded-xl border border-dashed border-blush-300 bg-blush-50/60 px-3.5 py-2.5 text-left text-sm text-chocolate-600 transition hover:bg-blush-50"
                            >
                                <Sparkles className="h-4 w-4 shrink-0 text-blush-500" />
                                <span>
                                    Add <span className="font-semibold text-chocolate-700">“{term.trim()}”</span> as a
                                    new ingredient
                                </span>
                            </button>
                        )}

                        {draft && (
                            <div className="mt-2.5 space-y-2.5 rounded-xl border border-blush-200 bg-blush-50/60 p-3.5">
                                <Field label="Name">
                                    <Input
                                        value={draft.name}
                                        onChange={(e) => setDraft({ ...draft, name: e.target.value })}
                                        autoFocus
                                    />
                                </Field>
                                <div className="grid grid-cols-2 gap-2.5">
                                    <Field label="Unit">
                                        <Select
                                            value={draft.unit}
                                            onChange={(e) => setDraft({ ...draft, unit: e.target.value })}
                                        >
                                            {units.map((u) => (
                                                <option key={u} value={u}>
                                                    {u}
                                                </option>
                                            ))}
                                        </Select>
                                    </Field>
                                    <Field label="Category">
                                        <Select
                                            value={draft.category}
                                            onChange={(e) => setDraft({ ...draft, category: e.target.value })}
                                        >
                                            {categories.map((c) => (
                                                <option key={c} value={c} className="capitalize">
                                                    {c}
                                                </option>
                                            ))}
                                        </Select>
                                    </Field>
                                </div>
                                <Field label="Price per unit">
                                    <Input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={draft.unit_price}
                                        onChange={(e) => setDraft({ ...draft, unit_price: e.target.value })}
                                        placeholder="0.00"
                                    />
                                </Field>
                                <div className="flex gap-2">
                                    <Button type="button" onClick={addDraft} className="flex-1">
                                        <Plus className="h-4 w-4" /> Add
                                    </Button>
                                    <Button type="button" variant="secondary" onClick={() => setDraft(null)}>
                                        Cancel
                                    </Button>
                                </div>
                            </div>
                        )}

                        <div className="mt-3 max-h-[26rem] space-y-1.5 overflow-y-auto pr-0.5">
                            {visible.length === 0 && !search && (
                                <p className="rounded-xl border border-dashed border-cream-300 px-4 py-8 text-center text-sm text-chocolate-400">
                                    No ingredients yet. Type a name above to add the first one.
                                </p>
                            )}

                            {visible.map((ingredient) => {
                                const added = inBasket(ingredient.id);

                                return (
                                    <button
                                        key={ingredient.id}
                                        type="button"
                                        onClick={() => addExisting(ingredient)}
                                        disabled={added}
                                        className={clsx(
                                            'flex w-full items-center justify-between gap-3 rounded-xl border px-3.5 py-2.5 text-left transition',
                                            added
                                                ? 'cursor-not-allowed border-cream-200 bg-cream-100/60 opacity-60'
                                                : 'border-cream-200 bg-vanilla hover:border-blush-300 hover:bg-blush-50/60',
                                        )}
                                    >
                                        <span className="min-w-0">
                                            <span className="block truncate text-sm font-medium text-chocolate-700">
                                                {ingredient.name}
                                            </span>
                                            <span className="block text-[11px] text-chocolate-400">
                                                per {ingredient.unit}
                                                {ingredient.last_purchased_on
                                                    ? ` · last ${ingredient.last_purchased_on}`
                                                    : ' · never bought'}
                                            </span>
                                        </span>
                                        <span className="shrink-0 text-sm font-semibold tabular-nums text-chocolate-600">
                                            {peso(ingredient.last_price)}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </Card>
                </section>

                {/* Basket */}
                <section className="space-y-4">
                    <Card className="card-pad">
                        <div className="flex items-center gap-2.5">
                            <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-caramel-soft/30 text-caramel-dark">
                                <ShoppingBasket className="h-4.5 w-4.5" />
                            </span>
                            <p className="text-sm font-semibold text-chocolate-700">
                                What we bought
                                {lines.length > 0 && (
                                    <span className="ml-1.5 text-chocolate-400">({lines.length})</span>
                                )}
                            </p>
                        </div>

                        {errors.items && <p className="mt-2 text-xs font-medium text-cherry">{errors.items}</p>}

                        {lines.length === 0 ? (
                            <div className="mt-4">
                                <EmptyState
                                    icon={ShoppingBasket}
                                    title="Nothing in the basket"
                                    description="Pick ingredients from the list on the left. Their last price fills in automatically — change it here if the supplier's price moved."
                                />
                            </div>
                        ) : (
                            <div className="mt-4 overflow-x-auto">
                                <table className="table-lileu min-w-full">
                                    <thead>
                                        <tr>
                                            <th>Ingredient</th>
                                            <th className="w-28 text-right">Qty</th>
                                            <th className="w-36 text-right">Price / unit</th>
                                            <th className="w-28 text-right">Total</th>
                                            <th className="w-10" />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {lines.map((line) => (
                                            <tr key={line.key}>
                                                <td>
                                                    <p className="font-medium text-chocolate-700">{line.name}</p>
                                                    <p className="text-[11px] text-chocolate-400">
                                                        per {line.unit}
                                                        {line.ingredient_id === null && (
                                                            <span className="ml-1.5 text-blush-500">· new</span>
                                                        )}
                                                    </p>
                                                </td>
                                                <td>
                                                    <Input
                                                        type="number"
                                                        step="0.001"
                                                        min="0"
                                                        value={line.quantity}
                                                        onChange={(e) =>
                                                            updateLine(line.key, { quantity: e.target.value })
                                                        }
                                                        className="text-right tabular-nums"
                                                    />
                                                </td>
                                                <td>
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        value={line.unit_price}
                                                        onChange={(e) =>
                                                            updateLine(line.key, { unit_price: e.target.value })
                                                        }
                                                        className={clsx(
                                                            'text-right tabular-nums',
                                                            priceMoved(line) && 'border-caramel bg-caramel-soft/20',
                                                        )}
                                                    />
                                                    {priceMoved(line) && (
                                                        <p className="mt-1 text-right text-[11px] text-caramel-dark">
                                                            was {peso(line.catalog_price)}
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="text-right font-semibold tabular-nums text-chocolate-700">
                                                    <Money value={lineTotal(line)} />
                                                </td>
                                                <td className="text-right">
                                                    <button
                                                        type="button"
                                                        onClick={() => removeLine(line.key)}
                                                        className="rounded-lg p-1.5 text-chocolate-400 transition hover:bg-cherry/10 hover:text-cherry"
                                                        aria-label={`Remove ${line.name}`}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Card>

                    <Card className="card-pad">
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Field label="Date" error={errors.purchased_on}>
                                <Input
                                    type="date"
                                    value={data.purchased_on}
                                    onChange={(e) => setData('purchased_on', e.target.value)}
                                />
                            </Field>
                            <Field label="Supplier" error={errors.supplier}>
                                <Input
                                    list="purchase-suppliers"
                                    value={data.supplier}
                                    onChange={(e) => setData('supplier', e.target.value)}
                                    placeholder="Negros Dairy Supply"
                                />
                                <datalist id="purchase-suppliers">
                                    {suppliers.map((s) => (
                                        <option key={s} value={s} />
                                    ))}
                                </datalist>
                            </Field>
                            <Field label="Reference" error={errors.reference} hint="Receipt or PO number">
                                <Input
                                    value={data.reference}
                                    onChange={(e) => setData('reference', e.target.value)}
                                    placeholder="OR-1042"
                                />
                            </Field>
                            <Field
                                label="Description"
                                error={errors.description}
                                hint="Left blank, we name it after the basket"
                            >
                                <Input
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Weekly market run"
                                />
                            </Field>
                            <Field label="Notes" error={errors.notes} className="sm:col-span-2">
                                <Textarea
                                    rows={2}
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                    placeholder="Anything worth remembering about this run."
                                />
                            </Field>
                        </div>

                        <div className="mt-5 flex flex-wrap items-center justify-between gap-4 border-t border-cream-200 pt-5">
                            <div>
                                <p className="text-sm text-chocolate-400">Purchase total</p>
                                <p className="font-display text-3xl font-semibold text-chocolate-700">
                                    <Money value={total} />
                                </p>
                            </div>
                            <Button type="submit" disabled={processing || lines.length === 0}>
                                <Plus className="h-4 w-4" /> Save purchase
                            </Button>
                        </div>
                    </Card>
                </section>
            </form>
        </AdminLayout>
    );
}
