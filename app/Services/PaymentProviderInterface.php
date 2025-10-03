<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentAttempt;

interface PaymentProviderInterface
{
    public function processPayment(Order $order, string $idempotencyKey, array $paymentData): PaymentAttempt;

    public function refundPayment(PaymentAttempt $payment): PaymentAttempt;
}
