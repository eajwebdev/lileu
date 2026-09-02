<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\ResellerOrder;
use App\Services\ReceiptService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

/**
 * View / print / PDF all render from ReceiptService's single payload, so the
 * browser receipt and the downloaded PDF cannot drift apart.
 *
 * A receipt is a *representation* of an existing payment. Nothing here writes
 * to the ledger, so reissuing one can never create a duplicate transaction.
 */
class ReceiptController extends Controller
{
    public function __construct(private ReceiptService $receipts) {}

    public function show(Request $request, Payment $payment): Response
    {
        $this->authorizePayment($request, $payment);

        return Inertia::render('Receipts/Show', [
            'data' => $this->receipts->forPayment($payment),
            'canPayBalance' => $this->canPayBalance($request, $payment),
        ]);
    }

    public function print(Request $request, Payment $payment): View
    {
        $this->authorizePayment($request, $payment);

        return view('receipts.reseller', [
            'data' => $this->receipts->forPayment($payment),
            'autoPrint' => true,
            'paper' => $request->string('paper')->toString() ?: 'a4',
        ]);
    }

    public function pdf(Request $request, Payment $payment): HttpResponse
    {
        $this->authorizePayment($request, $payment);

        $pdf = Pdf::loadView('receipts.reseller', [
            'data' => $this->receipts->forPayment($payment),
            'autoPrint' => false,
            'paper' => 'a4',
            'isPdf' => true,
        ])->setPaper('a4');

        return $pdf->download("{$payment->receipt_number}.pdf");
    }

    public function summary(Request $request, ResellerOrder $order): Response
    {
        $this->authorizeOrder($request, $order);

        return Inertia::render('Receipts/Summary', [
            'data' => $this->receipts->forOrder($order),
        ]);
    }

    public function summaryPdf(Request $request, ResellerOrder $order): HttpResponse
    {
        $this->authorizeOrder($request, $order);

        $pdf = Pdf::loadView('receipts.summary', [
            'data' => $this->receipts->forOrder($order),
            'isPdf' => true,
        ])->setPaper('a4');

        return $pdf->download("{$order->order_number}-payment-summary.pdf");
    }

    private function canPayBalance(Request $request, Payment $payment): bool
    {
        return $request->user()->isReseller()
            && (float) $payment->order->balance > 0
            && ! $payment->order->isCancelled();
    }

    private function authorizePayment(Request $request, Payment $payment): void
    {
        $this->authorizeOrder($request, $payment->order);
    }

    private function authorizeOrder(Request $request, ResellerOrder $order): void
    {
        $user = $request->user();

        if ($user->isAdmin() || $user->isCashier()) {
            return;
        }

        abort_unless($order->reseller_id === $user->reseller?->id, 403);
    }
}
