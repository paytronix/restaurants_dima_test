<?php

namespace App\Services\Payment\Providers;

use App\Contracts\Payment\PaymentProviderInterface;
use App\DTOs\Payment\PaymentResult;
use App\DTOs\Payment\PaymentStatusResult;
use App\DTOs\Payment\WebhookEventDTO;
use App\DTOs\Payment\WebhookVerificationResult;
use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StripePaymentProvider implements PaymentProviderInterface
{
    private string $secretKey;

    private string $webhookSecret;

    private string $apiBase = 'https://api.stripe.com/v1';

    public function __construct()
    {
        $this->secretKey = config('payments.stripe.secret', '');
        $this->webhookSecret = config('payments.stripe.webhook_secret', '');
    }

    public function getName(): string
    {
        return 'stripe';
    }

    public function createPayment(
        Order $order,
        int $amount,
        string $currency,
        string $idempotencyKey,
        array $context = []
    ): PaymentResult {
        if (empty($this->secretKey)) {
            return PaymentResult::failure('config_error', 'Stripe secret key not configured');
        }

        $response = Http::withBasicAuth($this->secretKey, '')
            ->withHeaders([
                'Idempotency-Key' => $idempotencyKey,
            ])
            ->asForm()
            ->post("{$this->apiBase}/payment_intents", [
                'amount' => $amount,
                'currency' => strtolower($currency),
                'metadata' => [
                    'order_id' => $order->id,
                ],
                'automatic_payment_methods' => [
                    'enabled' => 'true',
                ],
            ]);

        if (! $response->successful()) {
            $error = $response->json('error', []);
            Log::error('Stripe createPayment failed', [
                'order_id' => $order->id,
                'response' => $response->json(),
            ]);

            return PaymentResult::failure(
                $error['code'] ?? 'stripe_error',
                $error['message'] ?? 'Failed to create payment intent'
            );
        }

        $data = $response->json();

        return PaymentResult::success(
            providerPaymentId: $data['id'],
            checkoutUrl: null,
            clientSecret: $data['client_secret'] ?? null,
            status: $this->mapStripeStatus($data['status']),
            metadata: [
                'stripe_status' => $data['status'],
            ],
        );
    }

    public function confirmPayment(
        PaymentTransaction $transaction,
        array $context = []
    ): PaymentResult {
        if (empty($this->secretKey)) {
            return PaymentResult::failure('config_error', 'Stripe secret key not configured');
        }

        $paymentIntentId = $transaction->provider_payment_id;
        if (empty($paymentIntentId)) {
            return PaymentResult::failure('invalid_transaction', 'No provider payment ID');
        }

        $response = Http::withBasicAuth($this->secretKey, '')
            ->asForm()
            ->post("{$this->apiBase}/payment_intents/{$paymentIntentId}/confirm");

        if (! $response->successful()) {
            $error = $response->json('error', []);
            Log::error('Stripe confirmPayment failed', [
                'transaction_id' => $transaction->id,
                'response' => $response->json(),
            ]);

            return PaymentResult::failure(
                $error['code'] ?? 'stripe_error',
                $error['message'] ?? 'Failed to confirm payment'
            );
        }

        $data = $response->json();

        return PaymentResult::success(
            providerPaymentId: $data['id'],
            status: $this->mapStripeStatus($data['status']),
            metadata: [
                'stripe_status' => $data['status'],
            ],
        );
    }

    public function fetchPaymentStatus(PaymentTransaction $transaction): PaymentStatusResult
    {
        if (empty($this->secretKey)) {
            return PaymentStatusResult::failure('config_error', 'Stripe secret key not configured');
        }

        $paymentIntentId = $transaction->provider_payment_id;
        if (empty($paymentIntentId)) {
            return PaymentStatusResult::failure('invalid_transaction', 'No provider payment ID');
        }

        $response = Http::withBasicAuth($this->secretKey, '')
            ->get("{$this->apiBase}/payment_intents/{$paymentIntentId}");

        if (! $response->successful()) {
            $error = $response->json('error', []);
            Log::error('Stripe fetchPaymentStatus failed', [
                'transaction_id' => $transaction->id,
                'response' => $response->json(),
            ]);

            return PaymentStatusResult::failure(
                $error['code'] ?? 'stripe_error',
                $error['message'] ?? 'Failed to fetch payment status'
            );
        }

        $data = $response->json();

        return PaymentStatusResult::success(
            status: $this->mapStripeStatus($data['status']),
            providerStatus: $data['status'],
            rawResponse: $data,
        );
    }

    public function verifyWebhook(Request $request): WebhookVerificationResult
    {
        if (empty($this->webhookSecret)) {
            return WebhookVerificationResult::invalid('Webhook secret not configured');
        }

        $signature = $request->header('Stripe-Signature');
        if (empty($signature)) {
            return WebhookVerificationResult::invalid('Missing Stripe-Signature header');
        }

        $payload = $request->getContent();
        $signatureParts = $this->parseSignatureHeader($signature);

        if (! isset($signatureParts['t']) || ! isset($signatureParts['v1'])) {
            return WebhookVerificationResult::invalid('Invalid signature format');
        }

        $timestamp = $signatureParts['t'];
        $expectedSignature = $signatureParts['v1'];

        $tolerance = 300;
        if (abs(time() - (int) $timestamp) > $tolerance) {
            return WebhookVerificationResult::invalid('Timestamp outside tolerance');
        }

        $signedPayload = "{$timestamp}.{$payload}";
        $computedSignature = hash_hmac('sha256', $signedPayload, $this->webhookSecret);

        if (! hash_equals($computedSignature, $expectedSignature)) {
            return WebhookVerificationResult::invalid('Signature verification failed');
        }

        return WebhookVerificationResult::valid();
    }

    public function parseWebhook(Request $request): WebhookEventDTO
    {
        $payload = $request->all();
        $eventType = $payload['type'] ?? 'unknown';
        $eventId = $payload['id'] ?? '';

        $paymentIntentId = null;
        $status = null;

        if (isset($payload['data']['object'])) {
            $object = $payload['data']['object'];
            if (isset($object['id']) && str_starts_with($object['id'], 'pi_')) {
                $paymentIntentId = $object['id'];
            }
            if (isset($object['status'])) {
                $status = $this->mapStripeStatus($object['status']);
            }
        }

        return new WebhookEventDTO(
            eventId: $eventId,
            eventType: $eventType,
            providerPaymentId: $paymentIntentId,
            status: $status,
            rawPayload: $payload,
        );
    }

    private function mapStripeStatus(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'requires_payment_method', 'requires_confirmation', 'requires_action' => PaymentTransaction::STATUS_PENDING,
            'processing' => PaymentTransaction::STATUS_PROCESSING,
            'succeeded' => PaymentTransaction::STATUS_SUCCEEDED,
            'canceled' => PaymentTransaction::STATUS_CANCELLED,
            default => PaymentTransaction::STATUS_FAILED,
        };
    }

    private function parseSignatureHeader(string $header): array
    {
        $parts = [];
        foreach (explode(',', $header) as $item) {
            $itemParts = explode('=', $item, 2);
            if (count($itemParts) === 2) {
                $parts[$itemParts[0]] = $itemParts[1];
            }
        }

        return $parts;
    }
}
