<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_can_transition_to_paid(): void
    {
        $order = Order::factory()->pending()->create();

        $response = $this->patchJson("/api/v1/orders/{$order->id}/status", [
            'status' => 'paid',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('paid', $response->json('data.order.status'));
    }

    public function test_paid_can_transition_to_in_prep(): void
    {
        $order = Order::factory()->paid()->create();

        $response = $this->patchJson("/api/v1/orders/{$order->id}/status", [
            'status' => 'in_prep',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('in_prep', $response->json('data.order.status'));
    }

    public function test_in_prep_can_transition_to_ready(): void
    {
        $order = Order::factory()->inPrep()->create();

        $response = $this->patchJson("/api/v1/orders/{$order->id}/status", [
            'status' => 'ready',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('ready', $response->json('data.order.status'));
    }

    public function test_ready_can_transition_to_completed(): void
    {
        $order = Order::factory()->ready()->create();

        $response = $this->patchJson("/api/v1/orders/{$order->id}/status", [
            'status' => 'completed',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('completed', $response->json('data.order.status'));
    }

    public function test_pending_can_transition_to_cancelled(): void
    {
        $order = Order::factory()->pending()->create();

        $response = $this->patchJson("/api/v1/orders/{$order->id}/status", [
            'status' => 'cancelled',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('cancelled', $response->json('data.order.status'));
    }

    public function test_pending_can_transition_to_failed(): void
    {
        $order = Order::factory()->pending()->create();

        $response = $this->patchJson("/api/v1/orders/{$order->id}/status", [
            'status' => 'failed',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('failed', $response->json('data.order.status'));
    }

    public function test_cannot_transition_completed_to_pending(): void
    {
        $order = Order::factory()->completed()->create();

        $response = $this->patchJson("/api/v1/orders/{$order->id}/status", [
            'status' => 'pending',
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_transition_cancelled_to_paid(): void
    {
        $order = Order::factory()->cancelled()->create();

        $response = $this->patchJson("/api/v1/orders/{$order->id}/status", [
            'status' => 'paid',
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_transition_failed_to_in_prep(): void
    {
        $order = Order::factory()->failed()->create();

        $response = $this->patchJson("/api/v1/orders/{$order->id}/status", [
            'status' => 'in_prep',
        ]);

        $response->assertStatus(422);
    }

    public function test_any_status_can_transition_to_cancelled(): void
    {
        $statuses = [
            OrderStatus::PENDING,
            OrderStatus::PAID,
            OrderStatus::IN_PREP,
            OrderStatus::READY,
        ];

        foreach ($statuses as $status) {
            $order = Order::factory()->create(['status' => $status]);

            $response = $this->patchJson("/api/v1/orders/{$order->id}/status", [
                'status' => 'cancelled',
            ]);

            $response->assertStatus(200);
            $this->assertEquals('cancelled', $response->json('data.order.status'));
        }
    }
}
