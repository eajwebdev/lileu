<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\PayMongoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayMongoWebhookController extends Controller
{
    public function __construct(
        private PayMongoService $paymongo,
        private PaymentService $payments,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $signature = $request->header('Paymongo-Signature', '');

        if (! $this->paymongo->verifyWebhookSignature($signature, $request->getContent())) {
            Log::warning('Rejected PayMongo webhook with bad signature.');

            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $event = $request->input('data.attributes.type');
        $resource = $request->input('data.attributes.data', []);

        if (! in_array($event, ['payment.paid', 'qrph.payment.paid', 'link.payment.paid'], true)) {
            return response()->json(['message' => 'Ignored.']);
        }

        $payment = $this->resolvePayment($resource);

        if (! $payment) {
            Log::warning('PayMongo webhook could not be matched to a payment.', ['event' => $event]);

            return response()->json(['message' => 'Unmatched.'], 202);
        }

        // markPaid() is idempotent, so a redelivered webhook cannot double-post.
        $this->payments->markPaid($payment, [
            'reference' => data_get($resource, 'attributes.external_reference_number')
                ?? data_get($resource, 'id'),
            'payment_id' => data_get($resource, 'id'),
        ]);

        return response()->json(['message' => 'Recorded.']);
    }

    private function resolvePayment(array $resource): ?Payment
    {
        $receiptNumber = data_get($resource, 'attributes.metadata.receipt_number');

        if ($receiptNumber) {
            return Payment::where('receipt_number', $receiptNumber)->first();
        }

        $intentId = data_get($resource, 'attributes.payment_intent_id');

        return $intentId ? Payment::where('paymongo_source_id', $intentId)->first() : null;
    }
}
