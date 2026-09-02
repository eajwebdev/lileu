import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import clsx from 'clsx';
import { Cookie, Search } from 'lucide-react';
import SiteLayout from '@/Layouts/SiteLayout';
import { EmptyState, Input, SectionHeading } from '@/Components/Lileu/ui';
import { ProductCard } from '@/Components/Lileu/product';

export default function Catalog({ products, categories, filters }) {
    const [term, setTerm] = useState(filters.q ?? '');

    const applyFilters = (next) =>
        router.get(route('products.index'), next, { preserveState: true, replace: true });

    return (
        <SiteLayout>
            <Head title="All desserts" />

            <div className="mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-16">
                <SectionHeading
                    eyebrow="The menu"
                    title="Every cup we make"
                    description="Retail prices shown. Approved resellers see their own wholesale pricing inside the portal."
                />

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        applyFilters({ ...filters, q: term || undefined });
                    }}
                    className="mt-8 flex flex-wrap items-center gap-3"
                >
                    <div className="relative min-w-56 flex-1">
                        <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-chocolate-300" />
                        <Input
                            value={term}
                            onChange={(e) => setTerm(e.target.value)}
                            placeholder="Search a flavour…"
                            className="pl-10"
                        />
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <button
                            type="button"
                            onClick={() => applyFilters({ q: term || undefined })}
                            className={clsx(
                                'rounded-xl px-3.5 py-2 text-sm font-medium transition',
                                !filters.category
                                    ? 'bg-chocolate-700 text-cream-100'
                                    : 'bg-vanilla text-chocolate-500 hover:bg-cream-200',
                            )}
                        >
                            All
                        </button>
                        {categories.map((category) => (
                            <button
                                key={category.id}
                                type="button"
                                onClick={() => applyFilters({ q: term || undefined, category: category.slug })}
                                className={clsx(
                                    'rounded-xl px-3.5 py-2 text-sm font-medium transition',
                                    filters.category === category.slug
                                        ? 'bg-chocolate-700 text-cream-100'
                                        : 'bg-vanilla text-chocolate-500 hover:bg-cream-200',
                                )}
                            >
                                {category.name}
                            </button>
                        ))}
                    </div>
                </form>

                {products.length === 0 ? (
                    <EmptyState
                        icon={Cookie}
                        title="Nothing matches that yet"
                        description="Try a different flavour or clear the filters to see the whole menu."
                        action={
                            <Link href={route('products.index')} className="btn-secondary">
                                Clear filters
                            </Link>
                        }
                    />
                ) : (
                    <div className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        {products.map((product) => (
                            <ProductCard key={product.id} product={product} />
                        ))}
                    </div>
                )}
            </div>
        </SiteLayout>
    );
}
