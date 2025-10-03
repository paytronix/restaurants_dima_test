<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Events\OrderPaid;
use App\Models\Order;
use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\DB;

class PaymentServiceStub implements PaymentProviderInterface
{
    public function processPayment(Order $order, string $idempotencyKey, array $paymentData): PaymentAttempt
    {
        $existingAttempt = $this->checkIdempotency($idempotencyKey);

        if ($existingAttempt !== null) {
            return $existingAttempt;
        }

        if ($order->status !== OrderStatus::PENDING) {
            throw new \InvalidArgumentException('Order must be in PENDING status to process payment');
        }

        $amount = $paymentData['amount'] ?? $order->total;

        if ((float) $amount !== (float) $order->total) {
            throw new \InvalidArgumentException('Payment amount must match order total');
        }

        return DB::transaction(function () use ($order, $idempotencyKey, $amount) {
            $attempt = PaymentAttempt::create([
                'order_id' => $order->id,
                'idempotency_key' => $idempotencyKey,
                'amount' => $amount,
                'status' => 'pending',
                'provider_reference' => 'stub_' . uniqid(),
            ]);

            $successRate = (float) config('orders.payment_stub_success_rate', 1.0);
            $isSuccess = (mt_rand() / mt_getrandmax()) <= $successRate;

            if ($isSuccess) {
                $attempt->status = 'success';
                $attempt->save();

                $order->status = OrderStatus::PAID;
                $order->save();

                event(new OrderPaid($order, $attempt));
            } else {
                $attempt->status = 'failed';
                $attempt->error_message = 'Stub payment failed';
                $attempt->save();

                $order->status = OrderStatus::FAILED;
                $order->save();
            }

            return $attempt;
        });
    }

    public function refundPayment(PaymentAttempt $payment): PaymentAttempt
    {
        if ($payment->status !== 'success') {
            throw new \InvalidArgumentException('Can only refund successful payments');
        }

        $payment->status = 'refunded';
        $payment->save();

        return $payment;
    }

    public function checkIdempotency(string $key): ?PaymentAttempt
    {
        return PaymentAttempt::where('idempotency_key', $key)->first();
    }

    private function recordAttempt(Order $order, string $key, array $data): PaymentAttempt
    {
        return PaymentAttempt::create([
            'order_id' => $order->id,
            'idempotency_key' => $key,
            'amount' => $data['amount'],
            'status' => 'pending',
        ]);
    }
}
