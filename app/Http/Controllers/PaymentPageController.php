<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\PayMongoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PaymentPageController extends Controller
{
    public function __construct(
        private PayMongoService $paymongo,
        private PaymentService $payments,
    ) {}

    public function show(Request $request, Payment $payment): Response|RedirectResponse
    {
        $this->authorizePayment($request, $payment);

        if ($payment->isPaid()) {
            return redirect()->route('pay.success', $payment->receipt_number);
        }

        $gateway = $this->paymongo->createQrPhPayment($payment);
        $order = $payment->order()->with('reseller')->first();

        return Inertia::render('Payments/QrPh', [
            'payment' => [
                'receipt_number' => $payment->receipt_number,
                'kind' => $payment->kind,
                'kind_label' => $payment->kindLabel(),
                'amount' => (float) $payment->amount,
                'expires_at' => $payment->expires_at?->toIso8601String(),
            ],
            'order' => [
                'number' => $order->order_number,
                'total' => (float) $order->total,
                'amount_paid' => (float) $order->amount_paid,
                'balance_after' => round(max((float) $order->balance - (float) $payment->amount, 0), 2),
                'downpayment_percent' => (int) $order->downpayment_percent,
            ],
            // Only the QR image and the amount ever reach the browser — no keys,
            // no gateway payloads, no internal metadata.
            'qrSvg' => $this->qrSvg($gateway['qr_payload']),
            'checkoutUrl' => $gateway['checkout_url'],
            'demoMode' => $gateway['demo'],
        ]);
    }

    /**
     * Demo-mode confirmation. Real money always arrives through the signed
     * PayMongo webhook; this is refused the moment live keys are configured.
     */
    public function simulate(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorizePayment($request, $payment);

        abort_if($this->paymongo->isLive(), 403, 'Simulated payments are disabled when PayMongo is live.');

        $this->payments->markPaid($payment, ['reference' => 'DEMO-'.strtoupper(bin2hex(random_bytes(4)))]);

        return redirect()->route('pay.success', $payment->receipt_number);
    }

    /** Polled by the payment page so a webhook confirmation lands instantly. */
    public function status(Request $request, Payment $payment): JsonResponse
    {
        $this->authorizePayment($request, $payment);

        return response()->json([
            'status' => $payment->status,
            'redirect' => $payment->isPaid() ? route('pay.success', $payment->receipt_number) : null,
        ]);
    }

    public function success(Request $request, Payment $payment): Response
    {
        $this->authorizePayment($request, $payment);

        $order = $payment->order()->with('reseller')->first();

        return Inertia::render('Payments/Success', [
            'payment' => [
                'receipt_number' => $payment->receipt_number,
                'kind' => $payment->kind,
                'kind_label' => $payment->kindLabel(),
                'method_label' => $payment->methodLabel(),
                'amount' => (float) $payment->amount,
                'reference' => $payment->reference,
                'paid_on' => $payment->paid_at?->format('F j, Y'),
                'paid_time' => $payment->paid_at?->format('g:i A'),
                'status' => $payment->status,
            ],
            'order' => [
                'number' => $order->order_number,
                'total' => (float) $order->total,
                'amount_paid' => (float) $order->amount_paid,
                'balance' => (float) $order->balance,
                'payment_status' => $order->payment_status,
                'is_fully_paid' => $order->isFullyPaid(),
            ],
            'resellerFirstName' => explode(' ', trim($order->reseller->name))[0],
        ]);
    }

    private function qrSvg(string $payload): string
    {
        // SVG backend keeps this dependency-free — no imagick needed on XAMPP.
        return (string) QrCode::format('svg')
            ->size(280)
            ->margin(1)
            ->errorCorrection('M')
            ->color(59, 42, 34)
            ->backgroundColor(255, 253, 252)
            ->generate($payload);
    }

    private function authorizePayment(Request $request, Payment $payment): void
    {
        $user = $request->user();

        if ($user->isAdmin() || $user->isCashier()) {
            return;
        }

        abort_unless(
            $payment->order->reseller_id === $user->reseller?->id,
            403,
        );
    }
}
