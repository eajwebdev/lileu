import { Link } from '@inertiajs/react';
import { ArrowRight, ShoppingBag, Truck, Store } from 'lucide-react';
import PortalLayout from '@/Layouts/PortalLayout';
import {
    ButtonLink,
    EmptyState,
    Money,
    OrderStatusBadge,
    Pagination,
    PaymentStatusBadge,
} from '@/Components/Lileu/ui';

export default function Index({ orders }) {
    return (
        <PortalLayout
            title="My orders"
            subtitle="Every batch you have placed, and what is still owed on each."
            action={
                <ButtonLink href={route('portal.orders.create')}>
                    <ShoppingBag className="h-4 w-4" />
                    New order
                </ButtonLink>
            }
        >
            {orders.data.length === 0 ? (
                <EmptyState
                    icon={ShoppingBag}
                    title="No orders yet"
                    description="When you place your first order it will show up here with its payment status and receipts."
                    action={<ButtonLink href={route('portal.orders.create')}>Place an order</ButtonLink>}
                />
            ) : (
                <>
                    <div className="grid gap-3">
                        {orders.data.map((order) => {
                            const Fulfillment = order.fulfillment_type === 'delivery' ? Truck : Store;

                            return (
                                <Link
                                    key={order.number}
                                    href={route('portal.orders.show', order.number)}
                                    className="group rounded-2xl border border-cream-300/70 bg-vanilla p-4 shadow-soft transition hover:-translate-y-0.5 hover:shadow-lift sm:p-5"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-4">
                                        <div className="min-w-0">
                                            <p className="font-mono text-sm font-semibold text-chocolate-700">
                                                {order.number}
                                            </p>
                                            <p className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-chocolate-400">
                                                <span className="inline-flex items-center gap-1">
                                                    <Fulfillment className="h-3.5 w-3.5" />
                                                    {order.fulfillment_type}
                                                </span>
                                                <span>·</span>
                                                <span>{order.items_count} line items</span>
                                                <span>·</span>
                                                <span>placed {order.placed_on}</span>
                                                {order.date_needed && (
                                                    <>
                                                        <span>·</span>
                                                        <span>needed {order.date_needed}</span>
                                                    </>
                                                )}
                                            </p>
                                            <div className="mt-2.5 flex flex-wrap gap-2">
                                                <OrderStatusBadge status={order.status} />
                                                <PaymentStatusBadge status={order.payment_status} />
                                            </div>
                                        </div>

                                        <div className="text-right">
                                            <p className="font-display text-xl font-semibold text-chocolate-700">
                                                <Money value={order.total} />
                                            </p>
                                            <p className="text-xs text-chocolate-400">
                                                paid <Money value={order.amount_paid} />
                                            </p>
                                            {order.balance > 0 && (
                                                <p className="mt-1 inline-flex items-center gap-1 rounded-lg bg-caramel-soft/25 px-2 py-0.5 text-xs font-semibold text-caramel-dark">
                                                    <Money value={order.balance} /> due
                                                </p>
                                            )}
                                        </div>
                                    </div>

                                    <div className="mt-3 flex items-center justify-end border-t border-cream-200 pt-3 text-sm font-semibold text-chocolate-500 transition group-hover:text-chocolate-700">
                                        View order & receipts
                                        <ArrowRight className="ml-1.5 h-4 w-4 transition group-hover:translate-x-0.5" />
                                    </div>
                                </Link>
                            );
                        })}
                    </div>

                    <Pagination links={orders.links} className="mt-8" />
                </>
            )}
        </PortalLayout>
    );
}
