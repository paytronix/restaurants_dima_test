<?php

namespace App\Contracts\Payment;

use App\DTOs\Payment\PaymentResult;
use App\DTOs\Payment\PaymentStatusResult;
use App\DTOs\Payment\WebhookEventDTO;
use App\DTOs\Payment\WebhookVerificationResult;
use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;

interface PaymentProviderInterface
{
    public function getName(): string;

    public function createPayment(
        Order $order,
        int $amount,
        string $currency,
        string $idempotencyKey,
        array $context = []
    ): PaymentResult;

    public function confirmPayment(
        PaymentTransaction $transaction,
        array $context = []
    ): PaymentResult;

    public function fetchPaymentStatus(PaymentTransaction $transaction): PaymentStatusResult;

    public function verifyWebhook(Request $request): WebhookVerificationResult;

    public function parseWebhook(Request $request): WebhookEventDTO;
}
