<?php

namespace Tests\Feature\Api\V1;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhookEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $accessToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('Password123'),
            'status' => 'active',
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'Password123',
        ]);

        $this->accessToken = $loginResponse->json('data.access_token');
    }

    public function test_create_payment_requires_authentication(): void
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_DRAFT,
            'total' => 5000,
            'currency' => 'PLN',
        ]);

        $response = $this->postJson("/api/v1/orders/{$order->id}/payments");

        $response->assertStatus(401);
    }

    public function test_create_payment_requires_idempotency_key(): void
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_DRAFT,
            'total' => 5000,
            'currency' => 'PLN',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->postJson("/api/v1/orders/{$order->id}/payments");

        $response->assertStatus(400)
            ->assertJson([
                'detail' => 'Idempotency-Key header is required',
            ]);
    }

    public function test_create_payment_for_order(): void
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_DRAFT,
            'total' => 5000,
            'currency' => 'PLN',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->accessToken}",
            'Idempotency-Key' => 'test-key-123',
        ])->postJson("/api/v1/orders/{$order->id}/payments");

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'order_id',
                    'provider',
                    'status',
                    'amount',
                    'currency',
                ],
                'meta',
            ]);

        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'provider' => 'stub',
            'amount' => 5000,
            'currency' => 'PLN',
        ]);
    }

    public function test_create_payment_with_specific_provider(): void
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_DRAFT,
            'total' => 5000,
            'currency' => 'PLN',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->accessToken}",
            'Idempotency-Key' => 'test-key-456',
        ])->postJson("/api/v1/orders/{$order->id}/payments", [
            'provider' => 'stub',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.provider', 'stub');
    }

    public function test_create_payment_fails_for_non_payable_order(): void
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_PAID,
            'total' => 5000,
            'currency' => 'PLN',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->accessToken}",
            'Idempotency-Key' => 'test-key-789',
        ])->postJson("/api/v1/orders/{$order->id}/payments");

        $response->assertStatus(422)
            ->assertJsonPath('detail', 'Order is not in a payable state');
    }

    public function test_idempotency_returns_same_response_for_same_key(): void
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_DRAFT,
            'total' => 5000,
            'currency' => 'PLN',
        ]);

        $headers = [
            'Authorization' => "Bearer {$this->accessToken}",
            'Idempotency-Key' => 'idempotent-key-123',
        ];

        $response1 = $this->withHeaders($headers)
            ->postJson("/api/v1/orders/{$order->id}/payments");

        $response1->assertStatus(201);

        $order->update(['status' => Order::STATUS_DRAFT]);

        $response2 = $this->withHeaders($headers)
            ->postJson("/api/v1/orders/{$order->id}/payments");

        $response2->assertStatus(201);

        $this->assertEquals(
            $response1->json('data.id'),
            $response2->json('data.id')
        );

        $this->assertEquals(1, PaymentTransaction::where('order_id', $order->id)->count());
    }

    public function test_idempotency_conflict_for_different_payload(): void
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_DRAFT,
            'total' => 5000,
            'currency' => 'PLN',
        ]);

        $response1 = $this->withHeaders([
            'Authorization' => "Bearer {$this->accessToken}",
            'Idempotency-Key' => 'conflict-key-123',
        ])->postJson("/api/v1/orders/{$order->id}/payments", [
            'provider' => 'stub',
        ]);

        $order->update(['status' => Order::STATUS_DRAFT]);

        $response2 = $this->withHeaders([
            'Authorization' => "Bearer {$this->accessToken}",
            'Idempotency-Key' => 'conflict-key-123',
        ])->postJson("/api/v1/orders/{$order->id}/payments", [
            'provider' => 'stripe',
        ]);

        $response1->assertStatus(201);
        $response2->assertStatus(409)
            ->assertJson([
                'title' => 'Idempotency Conflict',
            ]);
    }

    public function test_get_payment_details(): void
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_PENDING_PAYMENT,
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

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->getJson("/api/v1/orders/{$order->id}/payments/{$transaction->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $transaction->id)
            ->assertJsonPath('data.status', 'succeeded');
    }

    public function test_get_payment_returns_404_for_wrong_order(): void
    {
        $order1 = Order::create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_PENDING_PAYMENT,
            'total' => 5000,
            'currency' => 'PLN',
        ]);

        $order2 = Order::create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_PENDING_PAYMENT,
            'total' => 6000,
            'currency' => 'PLN',
        ]);

        $transaction = PaymentTransaction::create([
            'order_id' => $order1->id,
            'provider' => 'stub',
            'provider_payment_id' => 'stub_123',
            'status' => PaymentTransaction::STATUS_SUCCEEDED,
            'amount' => 5000,
            'currency' => 'PLN',
            'idempotency_key_hash' => hash('sha256', 'test-key'),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->getJson("/api/v1/orders/{$order2->id}/payments/{$transaction->id}");

        $response->assertStatus(404);
    }

    public function test_legacy_pay_endpoint(): void
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_DRAFT,
            'total' => 5000,
            'currency' => 'PLN',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->accessToken}",
            'Idempotency-Key' => 'legacy-key-123',
        ])->postJson("/api/v1/orders/{$order->id}/pay");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'status',
                    'message',
                ],
                'meta',
            ])
            ->assertJsonPath('data.status', 'succeeded')
            ->assertJsonPath('data.message', 'Payment processed successfully');

        $order->refresh();
        $this->assertEquals(Order::STATUS_PAID, $order->status);
    }

    public function test_legacy_pay_requires_idempotency_key(): void
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_DRAFT,
            'total' => 5000,
            'currency' => 'PLN',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->postJson("/api/v1/orders/{$order->id}/pay");

        $response->assertStatus(400);
    }

    public function test_webhook_receives_and_stores_event(): void
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_PENDING_PAYMENT,
            'total' => 5000,
            'currency' => 'PLN',
        ]);

        $transaction = PaymentTransaction::create([
            'order_id' => $order->id,
            'provider' => 'stub',
            'provider_payment_id' => 'stub_payment_123',
            'status' => PaymentTransaction::STATUS_PENDING,
            'amount' => 5000,
            'currency' => 'PLN',
            'idempotency_key_hash' => hash('sha256', 'test-key'),
        ]);

        $response = $this->postJson('/api/v1/webhooks/payments/stub', [
            'event_id' => 'evt_123',
            'event_type' => 'payment.succeeded',
            'payment_id' => 'stub_payment_123',
            'status' => 'succeeded',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'received' => true,
                ],
            ]);

        $this->assertDatabaseHas('payment_webhook_events', [
            'provider' => 'stub',
            'event_id' => 'evt_123',
        ]);
    }

    public function test_webhook_deduplicates_events(): void
    {
        $response1 = $this->postJson('/api/v1/webhooks/payments/stub', [
            'event_id' => 'evt_duplicate',
            'event_type' => 'payment.succeeded',
        ]);

        $response2 = $this->postJson('/api/v1/webhooks/payments/stub', [
            'event_id' => 'evt_duplicate',
            'event_type' => 'payment.succeeded',
        ]);

        $response1->assertStatus(200);
        $response2->assertStatus(200);

        $this->assertEquals(1, PaymentWebhookEvent::where('event_id', 'evt_duplicate')->count());
    }

    public function test_webhook_returns_404_for_unknown_provider(): void
    {
        $response = $this->postJson('/api/v1/webhooks/payments/unknown', [
            'event_id' => 'evt_123',
        ]);

        $response->assertStatus(404);
    }
}
