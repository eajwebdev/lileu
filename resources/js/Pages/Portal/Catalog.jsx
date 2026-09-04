import { useState } from 'react';
import { Package, Search, ShoppingBag } from 'lucide-react';
import PortalLayout from '@/Layouts/PortalLayout';
import { ButtonLink, EmptyState, Input, Money } from '@/Components/Lileu/ui';
import { ProductImage } from '@/Components/Lileu/product';

export default function Catalog({ products, isCurated }) {
    const [term, setTerm] = useState('');

    const visible = products.filter(
        (p) =>
            !term ||
            p.label.toLowerCase().includes(term.toLowerCase()) ||
            p.sku.toLowerCase().includes(term.toLowerCase()),
    );

    return (
        <PortalLayout
            title="Your catalog"
            subtitle={
                isCurated
                    ? 'These are the products approved for your account, at your pricing.'
                    : 'Everything currently open to resellers, at wholesale pricing.'
            }
            action={
                <ButtonLink href={route('portal.orders.create')}>
                    <ShoppingBag className="h-4 w-4" />
                    New order
                </ButtonLink>
            }
        >
            <div className="relative mb-5 max-w-sm">
                <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-chocolate-300" />
                <Input
                    value={term}
                    onChange={(e) => setTerm(e.target.value)}
                    placeholder="Search a flavour…"
                    className="pl-10"
                />
            </div>

            {visible.length === 0 ? (
                <EmptyState
                    icon={Package}
                    title="Nothing here yet"
                    description="Once we approve products for your account they will appear here with your pricing."
                />
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {visible.map((product) => (
                        <div
                            key={product.key}
                            className="overflow-hidden rounded-2xl border border-cream-300/70 bg-vanilla shadow-soft"
                        >
                            <div className="aspect-[16/10] bg-cream-200">
                                <ProductImage product={product} />
                            </div>

                            <div className="p-4">
                                <div className="flex items-start justify-between gap-2">
                                    <h3 className="font-display font-semibold text-chocolate-700">{product.label}</h3>
                                    <span className="font-mono text-[11px] text-chocolate-300">{product.sku}</span>
                                </div>

                                {product.description && (
                                    <p className="mt-1 line-clamp-2 text-xs leading-relaxed text-chocolate-400">
                                        {product.description}
                                    </p>
                                )}

                                <dl className="mt-3 grid grid-cols-3 gap-2 border-t border-cream-200 pt-3 text-center">
                                    <div>
                                        <dt className="text-[10px] font-semibold uppercase tracking-wider text-chocolate-300">
                                            Your price
                                        </dt>
                                        <dd className="mt-0.5 text-sm font-semibold text-chocolate-700">
                                            <Money value={product.unit_price} />
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-[10px] font-semibold uppercase tracking-wider text-chocolate-300">
                                            Retail
                                        </dt>
                                        <dd className="mt-0.5 text-sm font-semibold text-chocolate-500">
                                            <Money value={product.retail_price} decimals={0} />
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-[10px] font-semibold uppercase tracking-wider text-chocolate-300">
                                            Margin
                                        </dt>
                                        <dd className="mt-0.5 text-sm font-semibold text-success">
                                            <Money value={product.margin} />
                                        </dd>
                                    </div>
                                </dl>

                                <div className="mt-3 flex items-center justify-between text-xs">
                                    <span className="text-chocolate-400">Min {product.min_qty} pcs</span>
                                    <span
                                        className={
                                            product.in_stock
                                                ? 'badge bg-success-light text-success'
                                                : 'badge bg-cherry/10 text-cherry-dark'
                                        }
                                    >
                                        {product.manually_unavailable
                                            ? 'Unavailable'
                                            : product.made_to_order
                                            ? 'Made to order'
                                            : product.in_stock
                                              ? 'Available'
                                              : 'Out of stock'}
                                    </span>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </PortalLayout>
    );
}
