<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * QR Ph via PayMongo.
 *
 * With no secret key configured the service runs in demo mode: it mints a local
 * QR payload so the whole reseller → pay → receipt journey is clickable during
 * development. Nothing from the gateway payload is ever exposed to a receipt.
 */
class PayMongoService
{
    public function isLive(): bool
    {
        return filled(config('lileu.paymongo.secret_key'));
    }

    /**
     * Creates the gateway intent for a pending payment and stores the handles
     * we need later. Returns the data the payment page renders.
     *
     * @return array{qr_payload:string, source_id:?string, checkout_url:?string, demo:bool}
     */
    public function createQrPhPayment(Payment $payment): array
    {
        if (! $this->isLive()) {
            return $this->demoPayload($payment);
        }

        try {
            $response = Http::withBasicAuth(config('lileu.paymongo.secret_key'), '')
                ->acceptJson()
                ->asJson()
                ->timeout(20)
                ->post(config('lileu.paymongo.base_url').'/payment_intents', [
                    'data' => [
                        'attributes' => [
                            // PayMongo works in centavos.
                            'amount' => (int) round(((float) $payment->amount) * 100),
                            'payment_method_allowed' => ['qrph'],
                            'currency' => $payment->currency,
                            'capture_type' => 'automatic',
                            'description' => sprintf(
                                '%s · %s',
                                $payment->order->order_number,
                                $payment->kindLabel(),
                            ),
                            'metadata' => [
                                'receipt_number' => $payment->receipt_number,
                                'order_number' => $payment->order->order_number,
                            ],
                        ],
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('PayMongo intent failed', ['status' => $response->status()]);

                return $this->demoPayload($payment, failedLive: true);
            }

            $data = $response->json('data', []);
            $attributes = $data['attributes'] ?? [];

            $payment->forceFill([
                'paymongo_source_id' => $data['id'] ?? null,
                'qr_payload' => $attributes['next_action']['code']['value']
                    ?? $attributes['client_key']
                    ?? $data['id']
                    ?? '',
                'meta' => ['client_key' => $attributes['client_key'] ?? null],
            ])->save();

            return [
                'qr_payload' => (string) $payment->qr_payload,
                'source_id' => $data['id'] ?? null,
                'checkout_url' => $attributes['next_action']['redirect']['url'] ?? null,
                'demo' => false,
            ];
        } catch (\Throwable $e) {
            Log::warning('PayMongo unreachable, falling back to demo QR', ['error' => $e->getMessage()]);

            return $this->demoPayload($payment, failedLive: true);
        }
    }

    /** Verifies the signature PayMongo sends with each webhook delivery. */
    public function verifyWebhookSignature(string $header, string $body): bool
    {
        $secret = config('lileu.paymongo.webhook_secret');

        if (blank($secret)) {
            return ! $this->isLive();
        }

        $parts = [];
        foreach (explode(',', $header) as $chunk) {
            [$key, $value] = array_pad(explode('=', trim($chunk), 2), 2, null);
            $parts[$key] = $value;
        }

        $timestamp = $parts['t'] ?? null;
        $signature = $parts['te'] ?? $parts['li'] ?? null;

        if (! $timestamp || ! $signature) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        return hash_equals($expected, $signature);
    }

    private function demoPayload(Payment $payment, bool $failedLive = false): array
    {
        $payload = sprintf(
            'PH.QRPH.DEMO|%s|%s|%s|%s',
            config('lileu.business.name'),
            $payment->receipt_number,
            number_format((float) $payment->amount, 2, '.', ''),
            Str::upper(Str::random(8)),
        );

        $payment->forceFill([
            'qr_payload' => $payload,
            'meta' => ['demo' => true, 'live_attempt_failed' => $failedLive],
        ])->save();

        return [
            'qr_payload' => $payload,
            'source_id' => null,
            'checkout_url' => null,
            'demo' => true,
        ];
    }
}
