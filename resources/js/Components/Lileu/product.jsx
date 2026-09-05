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
export function ProductPlate({ name, accent = 'blush', className, textClass = 'text-3xl' }) {
    const [from, to] = PLATE_TINTS[accent] ?? PLATE_TINTS.blush;

    return (
        <div
            className={clsx('flex h-full w-full items-center justify-center', className)}
            style={{ background: `radial-gradient(circle at 30% 25%, ${from}, ${to})` }}
        >
            <span className={clsx('font-display font-semibold text-chocolate-800/40', textClass)}>
                {name?.[0] ?? 'L'}
            </span>
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

/**
 * One thing a customer can actually pick.
 *
 * A product sold by flavour gets a card per flavour rather than a card with a
 * list tucked underneath it, so what is on the menu is what is on the screen:
 * its own picture, its own price, and its own name in the largest type.
 */
export function FlavourCard({ item }) {
    const accent = item.accent ?? 'blush';
    const soldOut = item.tracks_stock && item.stock <= 0;
    const status = ! item.is_available
        ? 'Unavailable'
        : ! item.tracks_stock
          ? 'Made to order'
          : soldOut
            ? 'Sold out'
            : 'Available';

    return (
        <Link
            href={`${route('products.show', item.slug)}${item.variant_id ? `#flavour-${item.variant_id}` : ''}`}
            className="group flex flex-col overflow-hidden rounded-3xl border border-cream-300/70 bg-cream-50 shadow-soft transition hover:-translate-y-1 hover:shadow-lift"
        >
            <div className="relative aspect-[4/3] overflow-hidden bg-cream-200">
                {item.image_url ? (
                    <img
                        src={item.image_url}
                        alt={item.variant_name ?? item.name}
                        className="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                    />
                ) : (
                    <ProductPlate name={item.variant_name ?? item.name} accent={accent} />
                )}

                <div className="absolute left-3 top-3 flex flex-wrap gap-2">
                    {item.category && (
                        <span className={clsx('badge', ACCENT_BADGE[accent] ?? ACCENT_BADGE.blush)}>
                            {item.category}
                        </span>
                    )}
                    {item.is_featured && (
                        <span className="badge bg-cherry text-white">
                            <Sparkles className="h-3 w-3" /> Bestseller
                        </span>
                    )}
                </div>
            </div>

            <div className="flex flex-1 flex-col p-5">
                {/* The flavour leads; the product it belongs to sits above it in
                    small type, which is how a customer asks for it at the counter. */}
                {item.variant_name && (
                    <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-chocolate-300">
                        {item.name}
                    </p>
                )}
                <h3 className="mt-0.5 font-display text-lg font-semibold text-chocolate-700">
                    {item.variant_name ?? item.name}
                </h3>
                {item.description && (
                    <p className="mt-1.5 line-clamp-2 text-sm leading-relaxed text-chocolate-400">
                        {item.description}
                    </p>
                )}

                <div className="mt-4 flex items-end justify-between border-t border-cream-300/70 pt-4">
                    <div>
                        <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-chocolate-300">
                            Retail
                        </p>
                        <p className="font-display text-xl font-semibold text-chocolate-700">
                            <Money value={item.retail_price} decimals={0} />
                        </p>
                    </div>
                    <span
                        className={clsx(
                            'badge',
                            status === 'Available' || status === 'Made to order'
                                ? 'bg-success-light text-success'
                                : 'bg-cherry/10 text-cherry-dark',
                        )}
                    >
                        {status}
                    </span>
                </div>
            </div>
        </Link>
    );
}
