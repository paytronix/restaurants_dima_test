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
use Illuminate\Support\Str;

class StubPaymentProvider implements PaymentProviderInterface
{
    public function getName(): string
    {
        return 'stub';
    }

    public function createPayment(
        Order $order,
        int $amount,
        string $currency,
        string $idempotencyKey,
        array $context = []
    ): PaymentResult {
        $paymentId = 'stub_'.Str::uuid()->toString();

        return PaymentResult::success(
            providerPaymentId: $paymentId,
            checkoutUrl: null,
            clientSecret: null,
            status: 'succeeded',
            metadata: [
                'stub' => true,
                'order_id' => $order->id,
            ],
        );
    }

    public function confirmPayment(
        PaymentTransaction $transaction,
        array $context = []
    ): PaymentResult {
        return PaymentResult::success(
            providerPaymentId: $transaction->provider_payment_id,
            status: 'succeeded',
        );
    }

    public function fetchPaymentStatus(PaymentTransaction $transaction): PaymentStatusResult
    {
        return PaymentStatusResult::success(
            status: PaymentTransaction::STATUS_SUCCEEDED,
            providerStatus: 'succeeded',
            rawResponse: ['stub' => true],
        );
    }

    public function verifyWebhook(Request $request): WebhookVerificationResult
    {
        return WebhookVerificationResult::valid();
    }

    public function parseWebhook(Request $request): WebhookEventDTO
    {
        $payload = $request->all();

        return new WebhookEventDTO(
            eventId: $payload['event_id'] ?? Str::uuid()->toString(),
            eventType: $payload['event_type'] ?? 'payment.succeeded',
            providerPaymentId: $payload['payment_id'] ?? null,
            status: $payload['status'] ?? 'succeeded',
            rawPayload: $payload,
        );
    }
}
