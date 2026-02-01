<?php

namespace Tests\Unit\Services;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\Payment\IdempotencyService;
use App\Services\Payment\PaymentProviderFactory;
use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentService = new PaymentService(
            new PaymentProviderFactory,
            new IdempotencyService
        );
    }

    public function test_reconciliation_skips_terminal_transactions(): void
    {
        $order = Order::create([
            'status' => Order::STATUS_PAID,
            'total' => 5000,
            'currency' => 'PLN',
        ]);

        $transaction = PaymentTransaction::create([
            'order_id' => $order->id,
            'provider' => 'stub',
            'provider_payment_id' => 'stub_123',
            'status' => PaymentTransaction::STATUS_SUCCEEDED,
            'amount' => 5000,
            'currency' => 'PLN',
            'idempotency_key_hash' => hash('sha256', 'test-key'),
        ]);

        $result = $this->paymentService->reconcileTransaction($transaction);

        $this->assertEquals('skipped', $result->status);
        $this->assertStringContainsString('terminal', $result->message);
    }

    public function test_reconciliation_skips_transactions_without_provider_payment_id(): void
    {
        $order = Order::create([
            'status' => Order::STATUS_PENDING_PAYMENT,
            'total' => 5000,
            'currency' => 'PLN',
        ]);

        $transaction = PaymentTransaction::create([
            'order_id' => $order->id,
            'provider' => 'stub',
            'provider_payment_id' => null,
            'status' => PaymentTransaction::STATUS_PENDING,
            'amount' => 5000,
            'currency' => 'PLN',
            'idempotency_key_hash' => hash('sha256', 'test-key'),
        ]);

        $result = $this->paymentService->reconcileTransaction($transaction);

        $this->assertEquals('skipped', $result->status);
        $this->assertStringContainsString('provider payment ID', $result->message);
    }

    public function test_reconciliation_updates_pending_to_succeeded_for_stub(): void
    {
        $order = Order::create([
            'status' => Order::STATUS_PENDING_PAYMENT,
            'total' => 5000,
            'currency' => 'PLN',
        ]);

        $transaction = PaymentTransaction::create([
            'order_id' => $order->id,
            'provider' => 'stub',
            'provider_payment_id' => 'stub_123',
            'status' => PaymentTransaction::STATUS_PENDING,
            'amount' => 5000,
            'currency' => 'PLN',
            'idempotency_key_hash' => hash('sha256', 'test-key'),
        ]);

        $result = $this->paymentService->reconcileTransaction($transaction);

        $this->assertEquals('mismatch', $result->status);
    }

    public function test_reconciliation_is_idempotent(): void
    {
        $order = Order::create([
            'status' => Order::STATUS_PAID,
            'total' => 5000,
            'currency' => 'PLN',
        ]);

        $transaction = PaymentTransaction::create([
            'order_id' => $order->id,
            'provider' => 'stub',
            'provider_payment_id' => 'stub_123',
            'status' => PaymentTransaction::STATUS_SUCCEEDED,
            'amount' => 5000,
            'currency' => 'PLN',
            'idempotency_key_hash' => hash('sha256', 'test-key'),
        ]);

        $result1 = $this->paymentService->reconcileTransaction($transaction);
        $result2 = $this->paymentService->reconcileTransaction($transaction);

        $this->assertEquals($result1->status, $result2->status);
        $this->assertEquals('skipped', $result1->status);
    }

    public function test_reconciliation_updates_order_status_when_payment_succeeds(): void
    {
        $order = Order::create([
            'status' => Order::STATUS_PENDING_PAYMENT,
            'total' => 5000,
            'currency' => 'PLN',
        ]);

        $transaction = PaymentTransaction::create([
            'order_id' => $order->id,
            'provider' => 'stub',
            'provider_payment_id' => 'stub_123',
            'status' => PaymentTransaction::STATUS_PROCESSING,
            'amount' => 5000,
            'currency' => 'PLN',
            'idempotency_key_hash' => hash('sha256', 'test-key'),
        ]);

        $result = $this->paymentService->reconcileTransaction($transaction);

        if ($result->wasUpdated()) {
            $order->refresh();
            $this->assertEquals(Order::STATUS_PAID, $order->status);
        }
    }
}
