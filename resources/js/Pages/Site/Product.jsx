import { Head, Link } from '@inertiajs/react';
import clsx from 'clsx';
import { ArrowLeft, ArrowRight } from 'lucide-react';
import SiteLayout from '@/Layouts/SiteLayout';
import { ButtonLink, Money } from '@/Components/Lileu/ui';
import { ACCENT_BADGE, ProductImage, ProductPlate } from '@/Components/Lileu/product';

export default function Product({ product, related }) {
    const accent = product.category?.accent ?? 'blush';

    return (
        <SiteLayout>
            <Head title={product.name} />

            <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-14">
                <Link
                    href={route('products.index')}
                    className="inline-flex items-center gap-1.5 text-sm font-medium text-chocolate-400 transition hover:text-chocolate-700"
                >
                    <ArrowLeft className="h-4 w-4" /> Back to all desserts
                </Link>

                <div className="mt-6 grid gap-10 lg:grid-cols-2">
                    <div className="overflow-hidden rounded-[2rem] border border-cream-300 bg-cream-200 shadow-soft">
                        <div className="aspect-square">
                            <ProductImage product={product} accent={accent} />
                        </div>
                    </div>

                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            {product.category && (
                                <span className={clsx('badge', ACCENT_BADGE[accent] ?? ACCENT_BADGE.blush)}>
                                    {product.category.name}
                                </span>
                            )}
                            <span
                                className={clsx(
                                    'badge',
                                    product.in_stock
                                        ? 'bg-success-light text-success'
                                        : 'bg-cherry/10 text-cherry-dark',
                                )}
                            >
                                {product.manually_unavailable
                                    ? 'Unavailable'
                                    : product.made_to_order
                                    ? 'Made to order'
                                    : product.in_stock
                                      ? 'Available now'
                                      : 'Sold out'}
                            </span>
                        </div>

                        <h1 className="mt-4 font-display text-3xl font-semibold tracking-tight text-chocolate-700 sm:text-4xl">
                            {product.name}
                        </h1>

                        {product.description && (
                            <p className="mt-4 text-base leading-relaxed text-chocolate-500">{product.description}</p>
                        )}

                        {/* Each flavour is priced and stocked on its own. */}
                        {product.has_variants && (
                            <div className="mt-8">
                                <p className="text-xs font-semibold uppercase tracking-[0.14em] text-chocolate-300">
                                    Flavours
                                </p>
                                <ul className="mt-3 space-y-2">
                                    {product.variants.map((variant) => (
                                        <li
                                            key={variant.id}
                                            // The menu links straight at a flavour, so give it
                                            // something to land on.
                                            id={`flavour-${variant.id}`}
                                            className={clsx(
                                                'scroll-mt-24',
                                                'flex items-center justify-between gap-4 rounded-2xl border px-4 py-3',
                                                variant.is_available
                                                    ? 'border-cream-300 bg-vanilla'
                                                    : 'border-cream-200 bg-cream-100/70 opacity-70',
                                            )}
                                        >
                                            <span className="min-w-0">
                                                <span className="block font-medium text-chocolate-700">
                                                    {variant.name}
                                                </span>
                                                {variant.description && (
                                                    <span className="block text-xs text-chocolate-400">
                                                        {variant.description}
                                                    </span>
                                                )}
                                            </span>
                                            <span className="shrink-0 text-right">
                                                <span className="block font-display text-lg font-semibold text-chocolate-700">
                                                    <Money value={variant.retail_price} decimals={0} />
                                                </span>
                                                <span
                                                    className={clsx(
                                                        'block text-[11px]',
                                                        variant.is_available
                                                            ? 'text-chocolate-400'
                                                            : 'text-cherry',
                                                    )}
                                                >
                                                    {variant.is_available
                                                        ? variant.made_to_order
                                                            ? 'Made to order'
                                                            : 'Available'
                                                        : 'Sold out'}
                                                </span>
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        <div className="mt-8">
                            <div className="rounded-2xl border border-cream-300 bg-vanilla p-5">
                                <p className="text-xs font-semibold uppercase tracking-[0.14em] text-chocolate-300">
                                    Retail price
                                </p>
                                <p className="mt-1 font-display text-3xl font-semibold text-chocolate-700">
                                    {product.has_variants && product.from_price !== product.to_price ? (
                                        <>
                                            <Money value={product.from_price} decimals={0} /> –{' '}
                                            <Money value={product.to_price} decimals={0} />
                                        </>
                                    ) : (
                                        <Money value={product.retail_price} decimals={0} />
                                    )}
                                </p>
                                <p className="mt-1 text-xs text-chocolate-400">What you pay at the counter.</p>
                            </div>
                        </div>

                        {/* Wholesale pricing is quoted to approved resellers in
                            their own portal, so the public page only invites
                            them to apply. */}
                        <div className="mt-7 flex flex-wrap gap-3">
                            <ButtonLink href={route('reseller.apply')} className="px-6 py-3 text-base">
                                Become a reseller
                                <ArrowRight className="h-4 w-4" />
                            </ButtonLink>
                        </div>
                    </div>
                </div>

                {related.length > 0 && (
                    <div className="mt-20">
                        <h2 className="section-title">More from this shelf</h2>
                        <div className="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                            {related.map((item) => (
                                <Link
                                    key={item.id}
                                    href={route('products.show', item.slug)}
                                    className="group overflow-hidden rounded-2xl border border-cream-300/70 bg-vanilla shadow-soft transition hover:-translate-y-1 hover:shadow-lift"
                                >
                                    <div className="aspect-[4/3] overflow-hidden bg-cream-200">
                                        {item.image_url ? (
                                            <img
                                                src={item.image_url}
                                                alt={item.name}
                                                className="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                                            />
                                        ) : (
                                            <ProductPlate name={item.name} accent={accent} />
                                        )}
                                    </div>
                                    <div className="p-4">
                                        <p className="font-display font-semibold text-chocolate-700">{item.name}</p>
                                        <p className="mt-0.5 text-sm text-chocolate-400">
                                            <Money value={item.retail_price} decimals={0} />
                                        </p>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </SiteLayout>
    );
}
