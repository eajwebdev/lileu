import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Download, Printer } from 'lucide-react';
import Receipt from '@/Components/Lileu/Receipt';
import { FlashToasts } from '@/Components/Lileu/ui';

export default function Summary({ data }) {
    const { auth } = usePage().props;
    const isStaff = auth?.user?.role === 'admin' || auth?.user?.role === 'cashier';
    const backHref = isStaff
        ? route('admin.orders.show', data.order.number)
        : route('portal.orders.show', data.order.number);

    return (
        <div className="min-h-screen bg-cream-fade py-8 print:bg-white print:py-0">
            <Head title={`Payment summary ${data.order.number}`} />
            <FlashToasts />

            <div className="mx-auto max-w-3xl px-4 print:max-w-none print:px-0">
                <div className="no-print mb-6 flex flex-wrap items-center justify-between gap-3">
                    <Link
                        href={backHref}
                        className="inline-flex items-center gap-1.5 text-sm font-medium text-chocolate-400 transition hover:text-chocolate-700"
                    >
                        <ArrowLeft className="h-4 w-4" /> Back to order
                    </Link>

                    <div className="flex flex-wrap gap-2">
                        <button type="button" onClick={() => window.print()} className="btn-ghost">
                            <Printer className="h-4 w-4" /> Print
                        </button>
                        <a href={route('receipts.summary.pdf', data.order.number)} className="btn-ghost">
                            <Download className="h-4 w-4" /> Download PDF
                        </a>
                    </div>
                </div>

                <Receipt data={data} variant="summary" />
            </div>
        </div>
    );
}
