import { Link, router, useForm } from '@inertiajs/react';
import { Banknote, Plus, ShoppingCart, Trash2 } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Button, ButtonLink, Card, Field, Input, Money, Select } from '@/Components/Lileu/ui';

const EXPENSE_CATEGORIES = ['operations', 'utilities', 'packaging', 'salaries', 'marketing', 'transport', 'other'];

export default function Index({ month, expenses, purchases, totals }) {
    const expenseForm = useForm({
        incurred_on: new Date().toISOString().slice(0, 10),
        category: 'operations',
        description: '',
        amount: '',
    });

    return (
        <AdminLayout
            title="Expenses"
            subtitle="Overhead alongside the month's ingredient buying, so net profit stays honest."
            action={
                <Input
                    type="month"
                    value={month}
                    onChange={(e) =>
                        router.get(route('admin.ledger.index'), { month: e.target.value }, { preserveState: true })
                    }
                    className="w-auto"
                />
            }
        >
            <div className="grid gap-5 xl:grid-cols-2">
                {/* Expenses */}
                <div className="space-y-4">
                    <Card className="card-pad">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-blush-100 text-blush-600">
                                    <Banknote className="h-5 w-5" />
                                </span>
                                <div>
                                    <p className="text-sm text-chocolate-400">Expenses this month</p>
                                    <p className="font-display text-2xl font-semibold text-chocolate-700">
                                        <Money value={totals.expenses} />
                                    </p>
                                </div>
                            </div>
                        </div>

                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                expenseForm.post(route('admin.expenses.store'), {
                                    preserveScroll: true,
                                    onSuccess: () => expenseForm.reset('description', 'amount'),
                                });
                            }}
                            className="mt-5 grid gap-3 border-t border-cream-200 pt-5 sm:grid-cols-2"
                        >
                            <Field label="Date" error={expenseForm.errors.incurred_on}>
                                <Input
                                    type="date"
                                    value={expenseForm.data.incurred_on}
                                    onChange={(e) => expenseForm.setData('incurred_on', e.target.value)}
                                />
                            </Field>
                            <Field label="Category" error={expenseForm.errors.category}>
                                <Select
                                    value={expenseForm.data.category}
                                    onChange={(e) => expenseForm.setData('category', e.target.value)}
                                >
                                    {EXPENSE_CATEGORIES.map((category) => (
                                        <option key={category} value={category} className="capitalize">
                                            {category}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <Field label="Description" error={expenseForm.errors.description} className="sm:col-span-2">
                                <Input
                                    value={expenseForm.data.description}
                                    onChange={(e) => expenseForm.setData('description', e.target.value)}
                                    placeholder="Electricity and water"
                                />
                            </Field>
                            <Field label="Amount" error={expenseForm.errors.amount}>
                                <Input
                                    type="number"
                                    step="0.01"
                                    value={expenseForm.data.amount}
                                    onChange={(e) => expenseForm.setData('amount', e.target.value)}
                                />
                            </Field>
                            <div className="flex items-end">
                                <Button type="submit" disabled={expenseForm.processing} className="w-full">
                                    <Plus className="h-4 w-4" /> Record expense
                                </Button>
                            </div>
                        </form>
                    </Card>

                    <Card className="card-pad">
                        {expenses.length === 0 ? (
                            <p className="rounded-xl border border-dashed border-cream-300 px-4 py-8 text-center text-sm text-chocolate-400">
                                No expenses recorded this month.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="table-lileu min-w-full">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Description</th>
                                            <th className="text-right">Amount</th>
                                            <th />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {expenses.map((expense) => (
                                            <tr key={expense.id}>
                                                <td className="whitespace-nowrap text-xs">{expense.incurred_on}</td>
                                                <td>
                                                    <p className="font-medium text-chocolate-700">
                                                        {expense.description}
                                                    </p>
                                                    <p className="text-[11px] capitalize text-chocolate-300">
                                                        {expense.category}
                                                    </p>
                                                </td>
                                                <td className="text-right font-semibold tabular-nums text-chocolate-700">
                                                    <Money value={expense.amount} />
                                                </td>
                                                <td className="text-right">
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            router.delete(
                                                                route('admin.expenses.destroy', expense.id),
                                                                { preserveScroll: true },
                                                            )
                                                        }
                                                        className="rounded-lg p-1.5 text-chocolate-400 transition hover:bg-cherry/10 hover:text-cherry"
                                                        aria-label="Remove expense"
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
                </div>

                {/* Purchases — written by the itemised ingredient flow */}
                <div className="space-y-4">
                    <Card className="card-pad">
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <div className="flex items-center gap-3">
                                <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-caramel-soft/30 text-caramel-dark">
                                    <ShoppingCart className="h-5 w-5" />
                                </span>
                                <div>
                                    <p className="text-sm text-chocolate-400">Ingredient purchases this month</p>
                                    <p className="font-display text-2xl font-semibold text-chocolate-700">
                                        <Money value={totals.purchases} />
                                    </p>
                                </div>
                            </div>
                            <ButtonLink href={route('admin.purchases.create')} variant="secondary">
                                <Plus className="h-4 w-4" /> Record purchase
                            </ButtonLink>
                        </div>

                        <p className="mt-4 border-t border-cream-200 pt-4 text-sm text-chocolate-400">
                            Purchases are recorded ingredient by ingredient, so this total is the sum of what was
                            actually bought. Manage the reusable list under{' '}
                            <Link
                                href={route('admin.ingredients.index')}
                                className="font-medium text-chocolate-600 underline decoration-blush-300 underline-offset-2 hover:text-chocolate-700"
                            >
                                Ingredients
                            </Link>
                            .
                        </p>
                    </Card>

                    <Card className="card-pad">
                        {purchases.length === 0 ? (
                            <p className="rounded-xl border border-dashed border-cream-300 px-4 py-8 text-center text-sm text-chocolate-400">
                                No purchases recorded this month.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="table-lileu min-w-full">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Description</th>
                                            <th className="text-right">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {purchases.map((purchase) => (
                                            <tr key={purchase.id}>
                                                <td className="whitespace-nowrap text-xs">{purchase.purchased_on}</td>
                                                <td>
                                                    <p className="font-medium text-chocolate-700">
                                                        {purchase.description}
                                                    </p>
                                                    <p className="text-[11px] text-chocolate-300">
                                                        {[
                                                            purchase.supplier,
                                                            purchase.reference,
                                                            `${purchase.item_count} ${purchase.item_count === 1 ? 'item' : 'items'}`,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' · ')}
                                                    </p>
                                                </td>
                                                <td className="text-right font-semibold tabular-nums text-chocolate-700">
                                                    <Money value={purchase.amount} />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {purchases.length > 0 && (
                            <Link
                                href={route('admin.purchases.index', { month })}
                                className="mt-4 block text-center text-sm font-medium text-chocolate-500 transition hover:text-chocolate-700"
                            >
                                Open purchases to see every line →
                            </Link>
                        )}
                    </Card>
                </div>
            </div>
        </AdminLayout>
    );
}
