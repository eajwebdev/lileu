import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import clsx from 'clsx';
import {
    ArrowRight,
    BadgeCheck,
    Croissant,
    HandCoins,
    Heart,
    QrCode,
    Sparkles,
    Star,
    Truck,
} from 'lucide-react';
import SiteLayout from '@/Layouts/SiteLayout';
import { ButtonLink, Money, SectionHeading } from '@/Components/Lileu/ui';
import { ACCENT_BADGE, ProductCard, ProductPlate } from '@/Components/Lileu/product';
import Reveal, { CountUp } from '@/Components/Lileu/Reveal';

const STEPS = [
    {
        icon: BadgeCheck,
        title: 'Apply once',
        body: 'Tell us about your area and how you plan to sell. We review every application by hand.',
    },
    {
        icon: Croissant,
        title: 'Order at reseller pricing',
        body: 'Get your approved catalog with wholesale prices and a clear margin on every piece.',
    },
    {
        icon: QrCode,
        title: 'Pay 50% by QR Ph',
        body: 'Secure your slot with a downpayment. Settle the balance any time before pickup.',
    },
    {
        icon: Truck,
        title: 'Pick up or get it delivered',
        body: 'Track preparation from your phone and collect a proper receipt for every payment.',
    },
];

const ACCENT_RING = {
    blush: 'hover:border-blush-300',
    caramel: 'hover:border-caramel',
    cherry: 'hover:border-cherry/50',
    chocolate: 'hover:border-chocolate-400',
    success: 'hover:border-success/50',
};

/** Slow drift on the hero artwork — enough to feel alive, not enough to distract. */
function useParallax() {
    const [offset, setOffset] = useState(0);

    useEffect(() => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return undefined;

        let frame;
        const onScroll = () => {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(() => setOffset(window.scrollY));
        };

        window.addEventListener('scroll', onScroll, { passive: true });

        return () => {
            window.removeEventListener('scroll', onScroll);
            cancelAnimationFrame(frame);
        };
    }, []);

    return offset;
}

function CategoryCard({ category, index }) {
    const accent = category.accent ?? 'blush';
    // One product can be several flavours on the shelf, so count those.
    const flavours = category.flavours_count ?? category.products_count;

    return (
        <Reveal delay={index * 90} variant="up" className="h-full">
            <Link
                href={route('products.index', { category: category.slug })}
                className={clsx(
                    'group relative flex h-full flex-col overflow-hidden rounded-3xl border-2 border-cream-300/70 bg-vanilla p-6 transition duration-500 hover:-translate-y-1.5 hover:shadow-lift',
                    ACCENT_RING[accent] ?? ACCENT_RING.blush,
                )}
            >
                {/* A peek at what is on this shelf */}
                <div className="mb-5 flex -space-x-4">
                    {(category.preview.length > 0 ? category.preview : [{ name: category.name }]).map((p, i) => (
                        <span
                            key={i}
                            className="h-16 w-16 overflow-hidden rounded-2xl border-2 border-vanilla bg-cream-200 shadow-soft transition duration-500 group-hover:-translate-y-1"
                            style={{ transitionDelay: `${i * 60}ms`, zIndex: 3 - i }}
                        >
                            {p.image_url ? (
                                <img src={p.image_url} alt="" className="h-full w-full object-cover" />
                            ) : (
                                <ProductPlate name={p.name} accent={accent} />
                            )}
                        </span>
                    ))}
                </div>

                <span className={clsx('badge w-fit', ACCENT_BADGE[accent] ?? ACCENT_BADGE.blush)}>
                    {flavours} {flavours === 1 ? 'flavour' : 'flavours'}
                </span>

                <h3 className="mt-3 font-display text-2xl font-semibold tracking-tight text-chocolate-700">
                    {category.name}
                </h3>

                {category.description && (
                    <p className="mt-1.5 text-sm leading-relaxed text-chocolate-400">{category.description}</p>
                )}

                <div className="mt-auto flex items-end justify-between border-t border-cream-200 pt-4">
                    {category.from_price > 0 && (
                        <span className="text-sm text-chocolate-400">
                            from{' '}
                            <strong className="font-display text-lg font-semibold text-chocolate-700">
                                <Money value={category.from_price} decimals={0} />
                            </strong>
                        </span>
                    )}
                    <span className="ml-auto inline-flex items-center gap-1.5 text-sm font-semibold text-chocolate-600 transition-all group-hover:gap-3">
                        Browse <ArrowRight className="h-4 w-4" />
                    </span>
                </div>
            </Link>
        </Reveal>
    );
}

export default function Landing({ featured, categories, stats }) {
    const { brand } = usePage().props;
    const scroll = useParallax();
    const town = brand?.address?.split(',')[1]?.trim() ?? brand?.address;

    return (
        <SiteLayout>
            <Head title="Sweets made in small batches" />

            {/* ── Hero ─────────────────────────────────────────────── */}
            <section className="relative overflow-hidden">
                <div
                    className="pointer-events-none absolute -right-32 -top-32 h-[30rem] w-[30rem] rounded-full bg-blush-200/45 blur-3xl"
                    style={{ transform: `translateY(${scroll * 0.12}px)` }}
                    aria-hidden
                />
                <div
                    className="pointer-events-none absolute -left-40 top-56 h-96 w-96 rounded-full bg-caramel-soft/25 blur-3xl"
                    style={{ transform: `translateY(${scroll * -0.08}px)` }}
                    aria-hidden
                />

                <div className="relative mx-auto grid max-w-6xl items-center gap-12 px-4 pb-20 pt-16 sm:px-6 sm:pb-28 sm:pt-24 lg:grid-cols-[1.05fr_0.95fr]">
                    <div>
                        <Reveal variant="fade" duration={600}>
                            <span className="badge bg-blush-100 text-blush-600">
                                <Heart className="h-3.5 w-3.5" /> {brand?.tagline} · {town}
                            </span>
                        </Reveal>

                        <Reveal delay={100}>
                            <h1 className="mt-5 font-display text-[2.6rem] font-semibold leading-[1.05] tracking-tight text-chocolate-700 sm:text-6xl lg:text-[4.25rem]">
                                Little sweets,
                                <span className="relative mx-2 inline-block">
                                    <span className="relative z-10">made</span>
                                    <span
                                        className="absolute inset-x-0 bottom-1.5 z-0 h-3.5 rounded-full bg-blush-200/80 sm:h-4"
                                        aria-hidden
                                    />
                                </span>
                                fresh every morning.
                            </h1>
                        </Reveal>

                        <Reveal delay={200}>
                            <p className="mt-6 max-w-xl text-base leading-relaxed text-chocolate-500 sm:text-lg">
                                Graham nests, cookies, munchkins and cream cups — layered and baked by hand in small
                                batches. Enjoy them yourself, or sell {brand?.name} in your own corner of Negros.
                            </p>
                        </Reveal>

                        <Reveal delay={300}>
                            <div className="mt-9 flex flex-wrap gap-3">
                                <ButtonLink
                                    href={route('reseller.apply')}
                                    className="group px-7 py-3.5 text-base shadow-lift"
                                >
                                    Become a reseller
                                    <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-1" />
                                </ButtonLink>
                                <ButtonLink
                                    href={route('products.index')}
                                    variant="ghost"
                                    className="px-7 py-3.5 text-base"
                                >
                                    See the menu
                                </ButtonLink>
                            </div>
                        </Reveal>

                        <Reveal delay={400}>
                            <dl className="mt-12 grid max-w-lg grid-cols-3 gap-4 border-t border-cream-300 pt-7">
                                <div>
                                    <dd className="font-display text-3xl font-semibold text-chocolate-700">
                                        <CountUp to={stats.flavours ?? stats.products} />
                                    </dd>
                                    <dt className="mt-0.5 text-xs font-medium uppercase tracking-[0.14em] text-chocolate-400">
                                        Flavours
                                    </dt>
                                </div>
                                <div>
                                    <dd className="font-display text-3xl font-semibold text-chocolate-700">
                                        <CountUp to={stats.resellers} />
                                    </dd>
                                    <dt className="mt-0.5 text-xs font-medium uppercase tracking-[0.14em] text-chocolate-400">
                                        Resellers
                                    </dt>
                                </div>
                                <div>
                                    <dd className="font-display text-3xl font-semibold text-chocolate-700">
                                        {stats.since?.replace('Est. ', '')}
                                    </dd>
                                    <dt className="mt-0.5 text-xs font-medium uppercase tracking-[0.14em] text-chocolate-400">
                                        Since
                                    </dt>
                                </div>
                            </dl>
                        </Reveal>
                    </div>

                    <Reveal variant="scale" duration={900} className="relative">
                        <div className="absolute inset-8 rounded-full bg-blush-200/40 blur-3xl" aria-hidden />
                        <div className="relative" style={{ transform: `translateY(${scroll * -0.05}px)` }}>
                            <img
                                src={brand?.logo}
                                alt={brand?.name}
                                className="mx-auto w-full max-w-md drop-shadow-2xl"
                            />
                        </div>

                        <div className="absolute -bottom-2 left-0 flex items-center gap-3 rounded-2xl border border-cream-300 bg-vanilla/95 px-4 py-3 shadow-lift backdrop-blur-sm sm:left-6">
                            <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-success-light text-success">
                                <HandCoins className="h-5 w-5" />
                            </span>
                            <span className="leading-tight">
                                <span className="block text-xs font-medium text-chocolate-400">Reseller margin</span>
                                <span className="block font-display text-base font-semibold text-chocolate-700">
                                    up to ₱3 / cup
                                </span>
                            </span>
                        </div>

                        <div className="absolute -top-2 right-0 flex items-center gap-2 rounded-2xl border border-cream-300 bg-vanilla/95 px-4 py-2.5 shadow-lift backdrop-blur-sm sm:right-4">
                            <Star className="h-4 w-4 fill-caramel text-caramel" />
                            <span className="text-sm font-semibold text-chocolate-700">Baked daily</span>
                        </div>
                    </Reveal>
                </div>
            </section>

            {/* ── Categories, straight from admin ───────────────────── */}
            {categories.length > 0 && (
                <section className="relative mx-auto max-w-6xl px-4 sm:px-6">
                    <Reveal>
                        <SectionHeading
                            eyebrow="What we make"
                            title="Pick your shelf"
                            description="Our line-up shifts with the season. Whatever the kitchen sets up shows here automatically."
                            action={
                                <ButtonLink href={route('products.index')} variant="ghost">
                                    All flavours
                                    <ArrowRight className="h-4 w-4" />
                                </ButtonLink>
                            }
                        />
                    </Reveal>

                    <div className="mt-9 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        {categories.map((category, i) => (
                            <CategoryCard key={category.id} category={category} index={i} />
                        ))}
                    </div>
                </section>
            )}

            {/* ── Featured ──────────────────────────────────────────── */}
            <section className="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-28">
                <Reveal>
                    <SectionHeading
                        eyebrow="Fresh from the kitchen"
                        title="The ones everyone reorders"
                        description="Layered by hand the morning they go out. Reseller pricing is available on all of them."
                    />
                </Reveal>

                <div className="mt-9 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    {featured.map((product, i) => (
                        <Reveal key={product.id} delay={(i % 4) * 90} className="h-full">
                            <ProductCard product={product} />
                        </Reveal>
                    ))}
                </div>
            </section>

            {/* ── Reseller programme ────────────────────────────────── */}
            <section className="relative overflow-hidden bg-cream-fade py-20 sm:py-28">
                <div
                    className="pointer-events-none absolute -right-24 top-10 h-80 w-80 rounded-full bg-blush-200/40 blur-3xl"
                    aria-hidden
                />

                <div className="relative mx-auto max-w-6xl px-4 sm:px-6">
                    <Reveal>
                        <SectionHeading
                            eyebrow="Reseller programme"
                            title="Start a sweet little business with us"
                            description="No franchise fee, no monthly quota. Order what you can sell, pay half to reserve it, and settle the rest before pickup."
                        />
                    </Reveal>

                    <div className="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        {STEPS.map((step, i) => (
                            <Reveal key={step.title} delay={i * 110} className="h-full">
                                <div className="group relative h-full rounded-3xl border border-cream-300/70 bg-vanilla p-6 shadow-soft transition duration-500 hover:-translate-y-1.5 hover:shadow-lift">
                                    <span className="absolute right-5 top-5 font-display text-4xl font-semibold text-cream-300 transition-colors duration-500 group-hover:text-blush-200">
                                        0{i + 1}
                                    </span>
                                    <span className="flex h-12 w-12 items-center justify-center rounded-2xl bg-blush-100 text-blush-600 transition duration-500 group-hover:scale-110 group-hover:bg-blush-200">
                                        <step.icon className="h-5 w-5" />
                                    </span>
                                    <h3 className="mt-5 font-display text-lg font-semibold text-chocolate-700">
                                        {step.title}
                                    </h3>
                                    <p className="mt-1.5 text-sm leading-relaxed text-chocolate-400">{step.body}</p>
                                </div>
                            </Reveal>
                        ))}
                    </div>

                    <Reveal delay={150} variant="scale">
                        <div className="mt-12 overflow-hidden rounded-[2rem] bg-choco-fade shadow-lift">
                            <div className="grid items-center gap-8 p-8 sm:p-14 lg:grid-cols-[1.4fr_1fr]">
                                <div>
                                    <span className="badge bg-cream-100/10 text-blush-300">
                                        <Sparkles className="h-3.5 w-3.5" /> Now taking applications
                                    </span>
                                    <h3 className="mt-4 font-display text-3xl font-semibold leading-tight text-cream-100 sm:text-4xl">
                                        Ready to sell {brand?.name} in your area?
                                    </h3>
                                    <p className="mt-4 max-w-xl text-sm leading-relaxed text-cream-200/70 sm:text-base">
                                        Applications are reviewed within a couple of days. Once approved you get your
                                        own portal, wholesale pricing, QR Ph payments and a proper receipt for every
                                        peso.
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-3 lg:justify-end">
                                    <ButtonLink
                                        href={route('reseller.apply')}
                                        variant="secondary"
                                        className="group px-7 py-3.5 text-base"
                                    >
                                        Apply now
                                        <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-1" />
                                    </ButtonLink>
                                    <ButtonLink
                                        href={route('login')}
                                        variant="ghost"
                                        className="border-cream-200/25 bg-transparent px-7 py-3.5 text-base text-cream-100 hover:bg-cream-100/10"
                                    >
                                        I have an account
                                    </ButtonLink>
                                </div>
                            </div>
                        </div>
                    </Reveal>
                </div>
            </section>
        </SiteLayout>
    );
}
