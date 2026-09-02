import { Link, usePage } from '@inertiajs/react';
import clsx from 'clsx';
import {
    ArrowRight,
    ChefHat,
    MessageCircle,
    PackageCheck,
    PackageOpen,
    Receipt,
    ShoppingBag,
    Wallet,
} from 'lucide-react';
import PortalLayout from '@/Layouts/PortalLayout';
import {
    ButtonLink,
    EmptyState,
    Money,
    OrderStatusBadge,
    PaymentStatusBadge,
} from '@/Components/Lileu/ui';

function StatCard({ icon: Icon, label, value, tint, href, hint }) {
    const body = (
        <>
            <span className={clsx('flex h-11 w-11 items-center justify-center rounded-xl', tint)}>
                <Icon className="h-5 w-5" />
            </span>
            <span className="mt-4 block font-display text-2xl font-semibold text-chocolate-700">{value}</span>
            <span className="mt-0.5 block text-sm font-medium text-chocolate-400">{label}</span>
            {hint && <span className="mt-1 block text-xs text-chocolate-300">{hint}</span>}
        </>
    );

    const className =
        'block rounded-2xl border border-cream-300/70 bg-vanilla p-5 shadow-soft transition hover:-translate-y-0.5 hover:shadow-lift';

    return href ? (
        <Link href={href} className={className}>
            {body}
        </Link>
    ) : (
        <div className={className}>{body}</div>
    );
}

export default function Dashboard({ cards, recentOrders }) {
    const { auth } = usePage().props;
    const firstName = auth?.user?.name?.split(' ')[0];

    return (
        <PortalLayout
            title={`Hi, ${firstName} 👋`}
            subtitle="Here is where your orders stand today."
            action={
                <ButtonLink href={route('portal.orders.create')}>
                    <ShoppingBag className="h-4 w-4" />
                    New order
                </ButtonLink>
            }
        >
            {/* Balance banner — the number a reseller most needs to see first. */}
            {cards.remaining_balance > 0 && (
                <div className="mb-6 overflow-hidden rounded-2xl bg-choco-fade">
                    <div className="flex flex-wrap items-center justify-between gap-4 p-6">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-[0.16em] text-blush-300">
                                Remaining balance
                            </p>
                            <p className="mt-1.5 font-display text-3xl font-semibold text-cream-100">
                                <Money value={cards.remaining_balance} />
                            </p>
                            <p className="mt-1 text-sm text-cream-200/60">
                                Across {cards.to_pay} order{cards.to_pay === 1 ? '' : 's'} still awaiting payment.
                            </p>
                        </div>
                        <ButtonLink href={route('portal.orders.index')} variant="secondary">
                            Settle balances
                            <ArrowRight className="h-4 w-4" />
                        </ButtonLink>
                    </div>
                </div>
            )}

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <StatCard
                    icon={PackageCheck}
                    label="Approved products"
                    value={cards.approved_products}
                    tint="bg-blush-100 text-blush-600"
                    href={route('portal.catalog')}
                />
                <StatCard
                    icon={Receipt}
                    label="Current orders"
                    value={cards.current_orders}
                    tint="bg-chocolate-100 text-chocolate-700"
                    href={route('portal.orders.index')}
                />
                <StatCard
                    icon={Wallet}
                    label="To pay"
                    value={cards.to_pay}
                    tint="bg-caramel-soft/30 text-caramel-dark"
                    href={route('portal.orders.index')}
                />
                <StatCard
                    icon={ChefHat}
                    label="Preparing"
                    value={cards.preparing}
                    tint="bg-caramel-soft/30 text-caramel-dark"
                />
                <StatCard
                    icon={PackageOpen}
                    label="Ready for pickup"
                    value={cards.ready}
                    tint="bg-success-light text-success"
                />
                <StatCard
                    icon={MessageCircle}
                    label="Messages"
                    value={cards.unread_messages}
                    hint={cards.unread_messages > 0 ? 'New replies waiting' : 'All caught up'}
                    tint="bg-blush-100 text-blush-600"
                    href={route('portal.messages.index')}
                />
            </div>

            <div className="mt-8">
                <div className="mb-4 flex items-end justify-between gap-3">
                    <h2 className="font-display text-xl font-semibold text-chocolate-700">Recent orders</h2>
                    <Link
                        href={route('portal.orders.index')}
                        className="inline-flex items-center gap-1.5 text-sm font-semibold text-chocolate-500 transition hover:text-chocolate-700"
                    >
                        See all <ArrowRight className="h-4 w-4" />
                    </Link>
                </div>

                {recentOrders.length === 0 ? (
                    <EmptyState
                        icon={ShoppingBag}
                        title="No orders yet"
                        description="Pick your flavours, choose a pickup date, and settle the downpayment to lock in your batch."
                        action={
                            <ButtonLink href={route('portal.orders.create')}>Place your first order</ButtonLink>
                        }
                    />
                ) : (
                    <div className="grid gap-3">
                        {recentOrders.map((order) => (
                            <Link
                                key={order.number}
                                href={route('portal.orders.show', order.number)}
                                className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-cream-300/70 bg-vanilla p-4 shadow-soft transition hover:-translate-y-0.5 hover:shadow-lift sm:p-5"
                            >
                                <div className="min-w-0">
                                    <p className="font-mono text-sm font-semibold text-chocolate-700">
                                        {order.number}
                                    </p>
                                    <p className="mt-0.5 text-xs text-chocolate-400">
                                        {order.item_count} pcs · placed {order.placed_on}
                                        {order.date_needed ? ` · needed ${order.date_needed}` : ''}
                                    </p>
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        <OrderStatusBadge status={order.status} />
                                        <PaymentStatusBadge status={order.payment_status} />
                                    </div>
                                </div>

                                <div className="text-right">
                                    <p className="font-display text-lg font-semibold text-chocolate-700">
                                        <Money value={order.total} />
                                    </p>
                                    {order.balance > 0 ? (
                                        <p className="text-xs font-medium text-caramel-dark">
                                            <Money value={order.balance} /> balance
                                        </p>
                                    ) : (
                                        <p className="text-xs font-medium text-success">Settled</p>
                                    )}
                                </div>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </PortalLayout>
    );
}
