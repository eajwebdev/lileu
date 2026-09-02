import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import clsx from 'clsx';
import { CheckCircle2, Info, X, XCircle } from 'lucide-react';

/* ------------------------------------------------------------------ */
/* Money                                                               */
/* ------------------------------------------------------------------ */

export function peso(value, { decimals = 2 } = {}) {
    const n = Number(value ?? 0);

    return `₱${n.toLocaleString('en-PH', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    })}`;
}

export function Money({ value, className, decimals = 2 }) {
    return <span className={clsx('tabular-nums', className)}>{peso(value, { decimals })}</span>;
}

/* ------------------------------------------------------------------ */
/* Brand mark                                                          */
/* ------------------------------------------------------------------ */

/**
 * The brand mark. The artwork is a circular plate on a transparent ground, so
 * it sits on a cream disc — invisible on light surfaces, and the thing that
 * keeps the chocolate rim readable against the dark navigation.
 */
export function Logo({ className = 'h-10 w-10', showWord = false, wordClassName = '' }) {
    const { brand } = usePage().props;

    return (
        <span className="inline-flex items-center gap-2.5">
            <span
                className={clsx(
                    'inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-cream-100',
                    className,
                )}
            >
                <img
                    src={brand?.logo_mark || '/images/logo-mark.png'}
                    alt=""
                    className="h-full w-full object-contain"
                />
            </span>
            {showWord && (
                <span className={clsx('font-display text-lg font-semibold tracking-tight', wordClassName)}>
                    {brand?.name || "Lil'Eu Sweets"}
                </span>
            )}
        </span>
    );
}

/* ------------------------------------------------------------------ */
/* Surfaces                                                            */
/* ------------------------------------------------------------------ */

export function Card({ className, children, as: As = 'div', ...props }) {
    return (
        <As className={clsx('card', className)} {...props}>
            {children}
        </As>
    );
}

export function SectionHeading({ eyebrow, title, description, action, className }) {
    return (
        <div className={clsx('flex flex-wrap items-end justify-between gap-4', className)}>
            <div>
                {eyebrow && (
                    <p className="mb-1 text-xs font-semibold uppercase tracking-[0.18em] text-blush-500">{eyebrow}</p>
                )}
                <h2 className="section-title">{title}</h2>
                {description && <p className="mt-1.5 max-w-2xl text-sm text-chocolate-500">{description}</p>}
            </div>
            {action}
        </div>
    );
}

/* ------------------------------------------------------------------ */
/* Status badges                                                       */
/* ------------------------------------------------------------------ */

const TONES = {
    green: 'bg-success-light text-success',
    'muted-green': 'bg-success-light/70 text-success',
    amber: 'bg-caramel-soft/30 text-caramel-dark',
    'muted-red': 'bg-cherry/10 text-cherry-dark',
    blush: 'bg-blush-100 text-blush-600',
    chocolate: 'bg-chocolate-100 text-chocolate-700',
    caramel: 'bg-caramel-soft/30 text-caramel-dark',
    cherry: 'bg-cherry/10 text-cherry-dark',
};

/** Order lifecycle → tone. Green is reserved for money and completion. */
const ORDER_STATUS = {
    pending: ['Pending', 'amber'],
    confirmed: ['Confirmed', 'blush'],
    preparing: ['Preparing', 'caramel'],
    ready: ['Ready', 'chocolate'],
    completed: ['Completed', 'green'],
    cancelled: ['Cancelled', 'muted-red'],
};

const PAYMENT_STATUS = {
    unpaid: ['Pending Payment', 'amber'],
    partially_paid: ['Partially Paid', 'amber'],
    downpayment_paid: ['Downpayment Paid', 'muted-green'],
    fully_paid: ['Fully Paid', 'green'],
    refunded: ['Refunded', 'muted-red'],
    void: ['Void', 'muted-red'],
    paid: ['Paid', 'green'],
    pending: ['Pending', 'amber'],
    failed: ['Failed', 'muted-red'],
    cancelled: ['Cancelled', 'muted-red'],
};

const RESELLER_STATUS = {
    pending: ['Pending Review', 'amber'],
    approved: ['Approved', 'green'],
    rejected: ['Rejected', 'muted-red'],
    suspended: ['Suspended', 'muted-red'],
};

export function Badge({ tone = 'blush', children, className }) {
    return <span className={clsx('badge', TONES[tone] ?? TONES.blush, className)}>{children}</span>;
}

export function OrderStatusBadge({ status, className }) {
    const [label, tone] = ORDER_STATUS[status] ?? [status, 'blush'];

    return (
        <Badge tone={tone} className={className}>
            {label}
        </Badge>
    );
}

export function PaymentStatusBadge({ status, className }) {
    const [label, tone] = PAYMENT_STATUS[status] ?? [status, 'blush'];

    return (
        <Badge tone={tone} className={className}>
            {label}
        </Badge>
    );
}

export function ResellerStatusBadge({ status, className }) {
    const [label, tone] = RESELLER_STATUS[status] ?? [status, 'blush'];

    return (
        <Badge tone={tone} className={className}>
            {label}
        </Badge>
    );
}

/* ------------------------------------------------------------------ */
/* Buttons / links                                                     */
/* ------------------------------------------------------------------ */

const VARIANTS = {
    primary: 'btn-primary',
    secondary: 'btn-secondary',
    ghost: 'btn-ghost',
    success: 'btn-success',
    danger: 'btn-danger',
};

export function Button({ variant = 'primary', className, as: As = 'button', ...props }) {
    return <As className={clsx(VARIANTS[variant], className)} {...props} />;
}

export function ButtonLink({ variant = 'primary', className, ...props }) {
    return <Link className={clsx(VARIANTS[variant], className)} {...props} />;
}

/* ------------------------------------------------------------------ */
/* Fields                                                              */
/* ------------------------------------------------------------------ */

export function Field({ label, error, hint, required, className, children }) {
    return (
        <div className={className}>
            {label && (
                <label className="label">
                    {label}
                    {required && <span className="ml-0.5 text-cherry">*</span>}
                </label>
            )}
            {children}
            {hint && !error && <p className="mt-1 text-xs text-chocolate-400">{hint}</p>}
            {error && <p className="mt-1 text-xs font-medium text-cherry">{error}</p>}
        </div>
    );
}

export function Input({ className, ...props }) {
    return <input className={clsx('input', className)} {...props} />;
}

export function Textarea({ className, ...props }) {
    return <textarea className={clsx('input', className)} {...props} />;
}

export function Select({ className, children, ...props }) {
    return (
        <select className={clsx('input', className)} {...props}>
            {children}
        </select>
    );
}

export function Toggle({ checked, onChange, label, description }) {
    return (
        <label className="flex cursor-pointer items-start gap-3">
            <input
                type="checkbox"
                checked={!!checked}
                onChange={(e) => onChange(e.target.checked)}
                className="mt-0.5 h-5 w-5 rounded-md border-cream-300 text-chocolate-700 focus:ring-blush-300"
            />
            <span>
                <span className="block text-sm font-medium text-chocolate-700">{label}</span>
                {description && <span className="block text-xs text-chocolate-400">{description}</span>}
            </span>
        </label>
    );
}

/* ------------------------------------------------------------------ */
/* Empty state                                                         */
/* ------------------------------------------------------------------ */

export function EmptyState({ icon: Icon, title, description, action }) {
    return (
        <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-blush-200 bg-blush-50/60 px-6 py-14 text-center">
            {Icon && (
                <span className="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-blush-100 text-blush-500">
                    <Icon className="h-7 w-7" />
                </span>
            )}
            <p className="font-display text-lg font-semibold text-chocolate-700">{title}</p>
            {description && <p className="mt-1.5 max-w-sm text-sm text-chocolate-400">{description}</p>}
            {action && <div className="mt-5">{action}</div>}
        </div>
    );
}

/* ------------------------------------------------------------------ */
/* Flash toasts                                                        */
/* ------------------------------------------------------------------ */

export function FlashToasts() {
    const { flash } = usePage().props;
    const [items, setItems] = useState([]);

    useEffect(() => {
        const next = [];
        if (flash?.success) next.push({ id: `s-${flash.success}`, tone: 'success', body: flash.success });
        if (flash?.error) next.push({ id: `e-${flash.error}`, tone: 'error', body: flash.error });
        if (flash?.info && !flash?.success) next.push({ id: `i-${flash.info}`, tone: 'info', body: flash.info });

        setItems(next);

        if (next.length === 0) return undefined;

        const timer = setTimeout(() => setItems([]), 5000);

        return () => clearTimeout(timer);
    }, [flash?.success, flash?.error, flash?.info]);

    if (items.length === 0) return null;

    const ICONS = { success: CheckCircle2, error: XCircle, info: Info };
    const STYLES = {
        success: 'border-success/25 bg-success-light text-success',
        error: 'border-cherry/25 bg-cherry/10 text-cherry-dark',
        info: 'border-blush-200 bg-blush-50 text-chocolate-700',
    };

    return (
        <div className="no-print pointer-events-none fixed inset-x-0 top-4 z-50 flex flex-col items-center gap-2 px-4">
            {items.map((item) => {
                const Icon = ICONS[item.tone];

                return (
                    <div
                        key={item.id}
                        className={clsx(
                            'pointer-events-auto flex w-full max-w-md animate-fade-up items-start gap-3 rounded-2xl border px-4 py-3 shadow-lift',
                            STYLES[item.tone],
                        )}
                    >
                        <Icon className="mt-0.5 h-5 w-5 shrink-0" />
                        <p className="flex-1 text-sm font-medium">{item.body}</p>
                        <button
                            type="button"
                            onClick={() => setItems((prev) => prev.filter((i) => i.id !== item.id))}
                            className="rounded-lg p-0.5 opacity-60 transition hover:opacity-100"
                            aria-label="Dismiss"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    </div>
                );
            })}
        </div>
    );
}

/* ------------------------------------------------------------------ */
/* Pagination                                                          */
/* ------------------------------------------------------------------ */

export function Pagination({ links, className }) {
    if (!links || links.length <= 3) return null;

    return (
        <nav className={clsx('flex flex-wrap items-center justify-center gap-1.5', className)}>
            {links.map((link, i) => (
                <Link
                    key={i}
                    href={link.url ?? '#'}
                    preserveScroll
                    className={clsx(
                        'min-w-9 rounded-lg px-3 py-2 text-center text-sm font-medium transition',
                        link.active
                            ? 'bg-chocolate-700 text-cream-100'
                            : link.url
                              ? 'bg-vanilla text-chocolate-600 hover:bg-cream-200'
                              : 'cursor-not-allowed text-chocolate-300',
                    )}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                />
            ))}
        </nav>
    );
}

/* ------------------------------------------------------------------ */
/* Modal                                                               */
/* ------------------------------------------------------------------ */

export function Modal({ open, onClose, title, description, children, footer, size = 'md' }) {
    useEffect(() => {
        if (!open) return undefined;

        const onKey = (e) => e.key === 'Escape' && onClose?.();
        document.addEventListener('keydown', onKey);
        document.body.style.overflow = 'hidden';

        return () => {
            document.removeEventListener('keydown', onKey);
            document.body.style.overflow = '';
        };
    }, [open, onClose]);

    if (!open) return null;

    const widths = { sm: 'max-w-md', md: 'max-w-2xl', lg: 'max-w-4xl' };

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            <div
                className="fixed inset-0 bg-chocolate-900/45 backdrop-blur-sm"
                onClick={onClose}
                aria-hidden
            />

            <div className="relative flex min-h-full items-center justify-center p-4">
                <div
                    role="dialog"
                    aria-modal="true"
                    className={clsx(
                        'w-full animate-fade-up overflow-hidden rounded-2xl border border-cream-300 bg-vanilla shadow-lift',
                        widths[size],
                    )}
                >
                    <div className="flex items-start justify-between gap-4 border-b border-cream-200 px-5 py-4">
                        <div>
                            <h2 className="font-display text-lg font-semibold text-chocolate-700">{title}</h2>
                            {description && <p className="mt-0.5 text-sm text-chocolate-400">{description}</p>}
                        </div>
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-lg p-1.5 text-chocolate-400 transition hover:bg-cream-200 hover:text-chocolate-700"
                            aria-label="Close"
                        >
                            <X className="h-5 w-5" />
                        </button>
                    </div>

                    <div className="max-h-[70vh] overflow-y-auto px-5 py-5">{children}</div>

                    {footer && (
                        <div className="flex justify-end gap-2 border-t border-cream-200 bg-cream-50 px-5 py-4">
                            {footer}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
