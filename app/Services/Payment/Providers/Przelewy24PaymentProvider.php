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
use Illuminate\Support\Str;

class Przelewy24PaymentProvider implements PaymentProviderInterface
{
    private string $merchantId;

    private string $crc;

    private string $apiKey;

    private bool $sandbox;

    private string $apiBase;

    public function __construct()
    {
        $this->merchantId = config('payments.p24.merchant_id', '');
        $this->crc = config('payments.p24.crc', '');
        $this->apiKey = config('payments.p24.api_key', '');
        $this->sandbox = config('payments.p24.sandbox', true);
        $this->apiBase = $this->sandbox
            ? 'https://sandbox.przelewy24.pl/api/v1'
            : 'https://secure.przelewy24.pl/api/v1';
    }

    public function getName(): string
    {
        return 'p24';
    }

    public function createPayment(
        Order $order,
        int $amount,
        string $currency,
        string $idempotencyKey,
        array $context = []
    ): PaymentResult {
        if (empty($this->merchantId) || empty($this->crc) || empty($this->apiKey)) {
            return PaymentResult::failure('config_error', 'Przelewy24 credentials not configured');
        }

        $sessionId = 'p24_'.Str::uuid()->toString();
        $sign = $this->calculateSign($sessionId, $this->merchantId, $amount, $currency);

        $returnUrl = $context['return_url'] ?? config('app.url').'/payment/return';
        $notifyUrl = $context['notify_url'] ?? config('app.url').'/api/v1/webhooks/payments/p24';

        $response = Http::withBasicAuth($this->merchantId, $this->apiKey)
            ->post("{$this->apiBase}/transaction/register", [
                'merchantId' => (int) $this->merchantId,
                'posId' => (int) $this->merchantId,
                'sessionId' => $sessionId,
                'amount' => $amount,
                'currency' => strtoupper($currency),
                'description' => "Order #{$order->id}",
                'email' => $context['email'] ?? 'customer@example.com',
                'country' => 'PL',
                'language' => 'pl',
                'urlReturn' => $returnUrl,
                'urlStatus' => $notifyUrl,
                'sign' => $sign,
            ]);

        if (! $response->successful()) {
            $error = $response->json('error', $response->json());
            Log::error('P24 createPayment failed', [
                'order_id' => $order->id,
                'response' => $response->json(),
            ]);

            return PaymentResult::failure(
                'p24_error',
                is_array($error) ? json_encode($error) : (string) $error
            );
        }

        $data = $response->json('data', []);
        $token = $data['token'] ?? null;

        if (empty($token)) {
            return PaymentResult::failure('p24_error', 'No token received from P24');
        }

        $checkoutUrl = $this->sandbox
            ? "https://sandbox.przelewy24.pl/trnRequest/{$token}"
            : "https://secure.przelewy24.pl/trnRequest/{$token}";

        return PaymentResult::success(
            providerPaymentId: $sessionId,
            checkoutUrl: $checkoutUrl,
            clientSecret: $token,
            status: 'pending',
            metadata: [
                'p24_token' => $token,
                'session_id' => $sessionId,
            ],
        );
    }

    public function confirmPayment(
        PaymentTransaction $transaction,
        array $context = []
    ): PaymentResult {
        return PaymentResult::success(
            providerPaymentId: $transaction->provider_payment_id,
            status: $transaction->status,
        );
    }

    public function fetchPaymentStatus(PaymentTransaction $transaction): PaymentStatusResult
    {
        if (empty($this->merchantId) || empty($this->apiKey)) {
            return PaymentStatusResult::failure('config_error', 'Przelewy24 credentials not configured');
        }

        $sessionId = $transaction->provider_payment_id;
        if (empty($sessionId)) {
            return PaymentStatusResult::failure('invalid_transaction', 'No provider payment ID');
        }

        $response = Http::withBasicAuth($this->merchantId, $this->apiKey)
            ->get("{$this->apiBase}/transaction/by/sessionId/{$sessionId}");

        if (! $response->successful()) {
            Log::error('P24 fetchPaymentStatus failed', [
                'transaction_id' => $transaction->id,
                'response' => $response->json(),
            ]);

            return PaymentStatusResult::failure(
                'p24_error',
                'Failed to fetch payment status'
            );
        }

        $data = $response->json('data', []);
        $status = $this->mapP24Status($data['status'] ?? 0);

        return PaymentStatusResult::success(
            status: $status,
            providerStatus: (string) ($data['status'] ?? 'unknown'),
            rawResponse: $data,
        );
    }

    public function verifyWebhook(Request $request): WebhookVerificationResult
    {
        if (empty($this->crc)) {
            return WebhookVerificationResult::invalid('CRC not configured');
        }

        $merchantId = $request->input('merchantId');
        $posId = $request->input('posId');
        $sessionId = $request->input('sessionId');
        $amount = $request->input('amount');
        $originAmount = $request->input('originAmount');
        $currency = $request->input('currency');
        $orderId = $request->input('orderId');
        $methodId = $request->input('methodId');
        $statement = $request->input('statement');
        $sign = $request->input('sign');

        if (empty($sign)) {
            return WebhookVerificationResult::invalid('Missing signature');
        }

        $signData = json_encode([
            'merchantId' => (int) $merchantId,
            'posId' => (int) $posId,
            'sessionId' => $sessionId,
            'amount' => (int) $amount,
            'originAmount' => (int) $originAmount,
            'currency' => $currency,
            'orderId' => (int) $orderId,
            'methodId' => (int) $methodId,
            'statement' => $statement,
            'crc' => $this->crc,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $expectedSign = hash('sha384', $signData);

        if (! hash_equals($expectedSign, $sign)) {
            return WebhookVerificationResult::invalid('Signature verification failed');
        }

        return WebhookVerificationResult::valid();
    }

    public function parseWebhook(Request $request): WebhookEventDTO
    {
        $payload = $request->all();
        $sessionId = $payload['sessionId'] ?? '';
        $orderId = $payload['orderId'] ?? '';

        $eventId = ! empty($orderId) ? "p24_{$orderId}" : 'p24_'.Str::uuid()->toString();

        $status = $this->mapP24Status($payload['status'] ?? 0);

        return new WebhookEventDTO(
            eventId: $eventId,
            eventType: 'payment.notification',
            providerPaymentId: $sessionId,
            status: $status,
            rawPayload: $payload,
        );
    }

    private function calculateSign(string $sessionId, string $merchantId, int $amount, string $currency): string
    {
        $data = json_encode([
            'sessionId' => $sessionId,
            'merchantId' => (int) $merchantId,
            'amount' => $amount,
            'currency' => strtoupper($currency),
            'crc' => $this->crc,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha384', $data);
    }

    private function mapP24Status(int $status): string
    {
        return match ($status) {
            0 => PaymentTransaction::STATUS_PENDING,
            1, 2 => PaymentTransaction::STATUS_PROCESSING,
            3 => PaymentTransaction::STATUS_SUCCEEDED,
            default => PaymentTransaction::STATUS_FAILED,
        };
    }
}
