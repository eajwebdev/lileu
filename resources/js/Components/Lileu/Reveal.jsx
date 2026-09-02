import { useEffect, useRef, useState } from 'react';
import clsx from 'clsx';

/** Respects the viewer's motion preference — no animation if they asked for none. */
function prefersReducedMotion() {
    return typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

const VARIANTS = {
    up: 'translate-y-8',
    down: '-translate-y-8',
    left: 'translate-x-10',
    right: '-translate-x-10',
    scale: 'scale-95',
    fade: '',
};

/**
 * Reveals its children the first time they scroll into view.
 *
 * IntersectionObserver plus a transform — no animation library, and it stays
 * inert for anyone who has reduced motion turned on.
 */
export default function Reveal({
    children,
    variant = 'up',
    delay = 0,
    duration = 700,
    className,
    as: As = 'div',
    threshold = 0.15,
}) {
    const ref = useRef(null);
    const [shown, setShown] = useState(false);

    useEffect(() => {
        if (prefersReducedMotion()) {
            setShown(true);

            return undefined;
        }

        const node = ref.current;

        if (!node) return undefined;

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    setShown(true);
                    observer.disconnect();
                }
            },
            { threshold, rootMargin: '0px 0px -40px 0px' },
        );

        observer.observe(node);

        return () => observer.disconnect();
    }, [threshold]);

    return (
        <As
            ref={ref}
            className={clsx(
                'transition-[opacity,transform] ease-out motion-reduce:transition-none',
                shown ? 'translate-x-0 translate-y-0 scale-100 opacity-100' : clsx('opacity-0', VARIANTS[variant]),
                className,
            )}
            style={{ transitionDuration: `${duration}ms`, transitionDelay: `${delay}ms` }}
        >
            {children}
        </As>
    );
}

/**
 * Counts up to a number once it scrolls into view. Used for the landing stats,
 * where a static figure reads flat next to everything else moving.
 */
export function CountUp({ to, duration = 1400, decimals = 0, prefix = '', suffix = '', className }) {
    const ref = useRef(null);
    const [value, setValue] = useState(0);

    useEffect(() => {
        const target = Number(to) || 0;

        if (prefersReducedMotion()) {
            setValue(target);

            return undefined;
        }

        const node = ref.current;

        if (!node) return undefined;

        let frame;

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (!entry.isIntersecting) return;

                observer.disconnect();
                const started = performance.now();

                const tick = (now) => {
                    const progress = Math.min((now - started) / duration, 1);
                    // Ease-out cubic, so it decelerates into the final figure.
                    setValue(target * (1 - Math.pow(1 - progress, 3)));

                    if (progress < 1) frame = requestAnimationFrame(tick);
                };

                frame = requestAnimationFrame(tick);
            },
            { threshold: 0.4 },
        );

        observer.observe(node);

        return () => {
            observer.disconnect();
            cancelAnimationFrame(frame);
        };
    }, [to, duration]);

    return (
        <span ref={ref} className={className}>
            {prefix}
            {value.toLocaleString('en-PH', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })}
            {suffix}
        </span>
    );
}
