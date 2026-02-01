<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(
        private PaymentProviderFactory $providerFactory,
        private IdempotencyService $idempotencyService,
    ) {}

    public function createPayment(
        Order $order,
        string $idempotencyKey,
        ?string $provider = null,
        ?int $amount = null,
    ): PaymentServiceResult {
        $amount = $amount ?? $order->total;
        $currency = $order->currency;
        $provider = $provider ?? config('payments.default', 'stub');

        $idempotencyCheck = $this->idempotencyService->checkIdempotency(
            $idempotencyKey,
            "payment_create_{$order->id}",
            ['order_id' => $order->id, 'provider' => $provider, 'amount' => $amount]
        );

        if ($idempotencyCheck->isConflict()) {
            return PaymentServiceResult::conflict();
        }

        if ($idempotencyCheck->hasCachedResponse()) {
            return PaymentServiceResult::cached($idempotencyCheck->cachedResponse);
        }

        if (! $order->isPayable()) {
            return PaymentServiceResult::failure('order_not_payable', 'Order is not in a payable state');
        }

        $idempotencyRecord = $idempotencyCheck->record;

        try {
            $paymentProvider = $this->providerFactory->make($provider);
            $result = $paymentProvider->createPayment($order, $amount, $currency, $idempotencyKey);

            if (! $result->success) {
                if ($idempotencyRecord !== null) {
                    $this->idempotencyService->markFailed($idempotencyRecord);
                }

                return PaymentServiceResult::failure($result->errorCode ?? 'provider_error', $result->errorMessage ?? 'Payment creation failed');
            }

            $transaction = PaymentTransaction::create([
                'order_id' => $order->id,
                'provider' => $provider,
                'provider_payment_id' => $result->providerPaymentId,
                'status' => $result->status ?? PaymentTransaction::STATUS_PENDING,
                'amount' => $amount,
                'currency' => $currency,
                'idempotency_key_hash' => $this->idempotencyService->getKeyHash($idempotencyKey),
                'checkout_url' => $result->checkoutUrl,
                'client_secret' => $result->clientSecret,
                'metadata_json' => $result->metadata,
            ]);

            if ($result->status === PaymentTransaction::STATUS_SUCCEEDED) {
                $order->markAsPaid();
            } elseif ($order->status === Order::STATUS_DRAFT) {
                $order->status = Order::STATUS_PENDING_PAYMENT;
                $order->save();
            }

            $responseData = [
                'data' => $this->formatTransactionResponse($transaction),
                'meta' => [],
                '_status_code' => 201,
            ];

            if ($idempotencyRecord !== null) {
                $this->idempotencyService->markCompleted($idempotencyRecord, $responseData);
            }

            return PaymentServiceResult::success($transaction, $responseData);

        } catch (\Exception $e) {
            Log::error('Payment creation failed', [
                'order_id' => $order->id,
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            if ($idempotencyRecord !== null) {
                $this->idempotencyService->markFailed($idempotencyRecord);
            }

            return PaymentServiceResult::failure('internal_error', 'An error occurred while processing the payment');
        }
    }

    public function processLegacyPay(
        Order $order,
        string $idempotencyKey,
        ?int $amount = null,
    ): PaymentServiceResult {
        return $this->createPayment($order, $idempotencyKey, 'stub', $amount);
    }

    public function getPayment(Order $order, int $paymentId): ?PaymentTransaction
    {
        return PaymentTransaction::where('order_id', $order->id)
            ->where('id', $paymentId)
            ->first();
    }

    public function processWebhook(string $provider, array $eventData): WebhookProcessResult
    {
        $eventId = $eventData['eventId'] ?? '';
        $eventType = $eventData['eventType'] ?? '';
        $providerPaymentId = $eventData['providerPaymentId'] ?? null;
        $status = $eventData['status'] ?? null;
        $rawPayload = $eventData['rawPayload'] ?? [];

        $existingEvent = PaymentWebhookEvent::where('provider', $provider)
            ->where('event_id', $eventId)
            ->first();

        if ($existingEvent !== null) {
            return WebhookProcessResult::duplicate();
        }

        $webhookEvent = PaymentWebhookEvent::create([
            'provider' => $provider,
            'event_id' => $eventId,
            'event_type' => $eventType,
            'signature_valid' => true,
            'payload_json' => $rawPayload,
            'received_at' => now(),
        ]);

        if (empty($providerPaymentId)) {
            $webhookEvent->markAsProcessed();

            return WebhookProcessResult::success('No payment ID in webhook');
        }

        $transaction = PaymentTransaction::where('provider', $provider)
            ->where('provider_payment_id', $providerPaymentId)
            ->first();

        if ($transaction === null) {
            $webhookEvent->markAsFailed('Transaction not found');

            return WebhookProcessResult::success('Transaction not found, event stored');
        }

        if ($status !== null && ! $transaction->isTerminal()) {
            $this->updateTransactionStatus($transaction, $status, $webhookEvent);
        }

        $webhookEvent->markAsProcessed();

        return WebhookProcessResult::success('Webhook processed successfully');
    }

    private function updateTransactionStatus(
        PaymentTransaction $transaction,
        string $newStatus,
        PaymentWebhookEvent $webhookEvent
    ): void {
        $oldStatus = $transaction->status;

        if ($oldStatus === $newStatus) {
            return;
        }

        if ($transaction->canTransitionTo($newStatus)) {
            DB::transaction(function () use ($transaction, $newStatus) {
                $transaction->transitionTo($newStatus);

                if ($newStatus === PaymentTransaction::STATUS_SUCCEEDED) {
                    $transaction->order->markAsPaid();
                }
            });

            Log::info('Payment status updated via webhook', [
                'transaction_id' => $transaction->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'webhook_event_id' => $webhookEvent->id,
            ]);
        } else {
            Log::warning('Invalid status transition attempted via webhook', [
                'transaction_id' => $transaction->id,
                'current_status' => $oldStatus,
                'attempted_status' => $newStatus,
                'webhook_event_id' => $webhookEvent->id,
            ]);
        }
    }

    public function reconcileTransaction(PaymentTransaction $transaction): ReconciliationResult
    {
        if ($transaction->isTerminal()) {
            return ReconciliationResult::skipped('Transaction already in terminal state');
        }

        if (empty($transaction->provider_payment_id)) {
            return ReconciliationResult::skipped('No provider payment ID');
        }

        try {
            $provider = $this->providerFactory->make($transaction->provider);
            $statusResult = $provider->fetchPaymentStatus($transaction);

            if (! $statusResult->success) {
                return ReconciliationResult::failure($statusResult->errorMessage ?? 'Failed to fetch status');
            }

            $providerStatus = $statusResult->status;
            $localStatus = $transaction->status;

            if ($providerStatus === $localStatus) {
                return ReconciliationResult::unchanged();
            }

            if ($transaction->canTransitionTo($providerStatus)) {
                DB::transaction(function () use ($transaction, $providerStatus) {
                    $transaction->transitionTo($providerStatus);

                    if ($providerStatus === PaymentTransaction::STATUS_SUCCEEDED) {
                        $transaction->order->markAsPaid();
                    }
                });

                return ReconciliationResult::updated($localStatus, $providerStatus);
            }

            return ReconciliationResult::mismatch($localStatus, $providerStatus);

        } catch (\Exception $e) {
            Log::error('Reconciliation failed', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            return ReconciliationResult::failure($e->getMessage());
        }
    }

    private function formatTransactionResponse(PaymentTransaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'order_id' => $transaction->order_id,
            'provider' => $transaction->provider,
            'status' => $transaction->status,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'checkout_url' => $transaction->checkout_url,
            'client_secret' => $transaction->client_secret,
            'created_at' => $transaction->created_at?->toIso8601String(),
            'updated_at' => $transaction->updated_at?->toIso8601String(),
        ];
    }
}

class PaymentServiceResult
{
    private function __construct(
        public readonly bool $success,
        public readonly ?PaymentTransaction $transaction = null,
        public readonly ?array $responseData = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly bool $isConflict = false,
        public readonly bool $isCached = false,
    ) {}

    public static function success(PaymentTransaction $transaction, array $responseData): self
    {
        return new self(true, $transaction, $responseData);
    }

    public static function cached(array $responseData): self
    {
        return new self(true, responseData: $responseData, isCached: true);
    }

    public static function conflict(): self
    {
        return new self(false, isConflict: true);
    }

    public static function failure(string $errorCode, string $errorMessage): self
    {
        return new self(false, errorCode: $errorCode, errorMessage: $errorMessage);
    }
}

class WebhookProcessResult
{
    private function __construct(
        public readonly bool $success,
        public readonly bool $isDuplicate,
        public readonly ?string $message = null,
    ) {}

    public static function success(string $message): self
    {
        return new self(true, false, $message);
    }

    public static function duplicate(): self
    {
        return new self(true, true, 'Duplicate event');
    }

    public static function failure(string $message): self
    {
        return new self(false, false, $message);
    }
}

class ReconciliationResult
{
    private function __construct(
        public readonly string $status,
        public readonly ?string $message = null,
        public readonly ?string $oldStatus = null,
        public readonly ?string $newStatus = null,
    ) {}

    public static function unchanged(): self
    {
        return new self('unchanged', 'Status matches provider');
    }

    public static function updated(string $oldStatus, string $newStatus): self
    {
        return new self('updated', "Status changed from {$oldStatus} to {$newStatus}", $oldStatus, $newStatus);
    }

    public static function mismatch(string $localStatus, string $providerStatus): self
    {
        return new self('mismatch', "Cannot transition from {$localStatus} to {$providerStatus}", $localStatus, $providerStatus);
    }

    public static function skipped(string $reason): self
    {
        return new self('skipped', $reason);
    }

    public static function failure(string $message): self
    {
        return new self('failure', $message);
    }

    public function wasUpdated(): bool
    {
        return $this->status === 'updated';
    }

    public function hasMismatch(): bool
    {
        return $this->status === 'mismatch';
    }
}
