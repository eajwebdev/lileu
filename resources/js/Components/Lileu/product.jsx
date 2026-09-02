import { Link } from '@inertiajs/react';
import clsx from 'clsx';
import { Sparkles } from 'lucide-react';
import { Money } from '@/Components/Lileu/ui';

export const ACCENT_BADGE = {
    blush: 'bg-blush-200 text-chocolate-800',
    caramel: 'bg-caramel-soft text-chocolate-800',
    cherry: 'bg-cherry text-white',
    chocolate: 'bg-chocolate-700 text-cream-100',
    success: 'bg-success-light text-success',
};

const PLATE_TINTS = {
    blush: ['#F7CED3', '#EFAFB8'],
    caramel: ['#E7BA7B', '#D7973E'],
    cherry: ['#F0B7BC', '#E0505C'],
    chocolate: ['#CDBAAE', '#8A6B5A'],
    success: ['#CFE5D6', '#5AA372'],
};

/**
 * Warm placeholder plate. Product photography is the star of this brand, so an
 * image-less product still gets a dessert-toned surface instead of grey.
 */
export function ProductPlate({ name, accent = 'blush', className }) {
    const [from, to] = PLATE_TINTS[accent] ?? PLATE_TINTS.blush;

    return (
        <div
            className={clsx('flex h-full w-full items-center justify-center', className)}
            style={{ background: `radial-gradient(circle at 30% 25%, ${from}, ${to})` }}
        >
            <span className="font-display text-3xl font-semibold text-chocolate-800/40">{name?.[0] ?? 'L'}</span>
        </div>
    );
}

export function ProductImage({ product, accent = 'blush', className }) {
    return product.image_url ? (
        <img src={product.image_url} alt={product.name} className={clsx('h-full w-full object-cover', className)} />
    ) : (
        <ProductPlate name={product.name} accent={accent} />
    );
}

export function ProductCard({ product }) {
    const accent = product.category?.accent ?? 'blush';

    return (
        <Link
            href={route('products.show', product.slug)}
            className="group flex flex-col overflow-hidden rounded-3xl border border-cream-300/70 bg-cream-50 shadow-soft transition hover:-translate-y-1 hover:shadow-lift"
        >
            <div className="relative aspect-[4/3] overflow-hidden bg-cream-200">
                <ProductImage
                    product={product}
                    accent={accent}
                    className="transition duration-500 group-hover:scale-105"
                />

                <div className="absolute left-3 top-3 flex flex-wrap gap-2">
                    {product.category && (
                        <span className={clsx('badge', ACCENT_BADGE[accent] ?? ACCENT_BADGE.blush)}>
                            {product.category.name}
                        </span>
                    )}
                    {product.is_featured && (
                        <span className="badge bg-cherry text-white">
                            <Sparkles className="h-3 w-3" /> Bestseller
                        </span>
                    )}
                </div>
            </div>

            <div className="flex flex-1 flex-col p-5">
                <h3 className="font-display text-lg font-semibold text-chocolate-700">{product.name}</h3>
                {product.description && (
                    <p className="mt-1.5 line-clamp-2 text-sm leading-relaxed text-chocolate-400">
                        {product.description}
                    </p>
                )}

                <div className="mt-4 flex items-end justify-between border-t border-cream-300/70 pt-4">
                    <div>
                        <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-chocolate-300">
                            Retail
                        </p>
                        <p className="font-display text-xl font-semibold text-chocolate-700">
                            <Money value={product.retail_price} decimals={0} />
                        </p>
                    </div>
                    <span
                        className={clsx(
                            'badge',
                            product.in_stock ? 'bg-success-light text-success' : 'bg-cherry/10 text-cherry-dark',
                        )}
                    >
                        {product.manually_unavailable
                            ? 'Unavailable'
                            : product.made_to_order
                              ? 'Made to order'
                              : product.in_stock
                                ? 'Available'
                                : 'Sold out'}
                    </span>
                </div>
            </div>
        </Link>
    );
}
