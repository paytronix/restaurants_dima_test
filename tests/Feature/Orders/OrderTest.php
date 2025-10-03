<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_order_details(): void
    {
        $order = Order::factory()->create();

        $response = $this->getJson("/api/v1/orders/{$order->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'order' => [
                    'id',
                    'order_number',
                    'status',
                    'subtotal',
                    'tax',
                    'total',
                    'customer_name',
                    'customer_email',
                    'customer_phone',
                    'items',
                    'payment_attempts',
                ],
            ],
            'meta' => ['trace_id'],
        ]);
    }

    public function test_authenticated_user_can_list_their_orders(): void
    {
        $user = User::factory()->create();
        Order::factory()->count(3)->create(['user_id' => $user->id]);
        Order::factory()->count(2)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/orders');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['orders'],
            'meta' => [
                'current_page',
                'per_page',
                'total',
                'last_page',
                'trace_id',
            ],
        ]);
        $this->assertCount(3, $response->json('data.orders'));
    }

    public function test_unauthenticated_user_cannot_list_orders(): void
    {
        $response = $this->getJson('/api/v1/orders');

        $response->assertStatus(401);
        $response->assertJsonFragment(['title' => 'Unauthorized']);
    }

    public function test_can_filter_orders_by_status(): void
    {
        $user = User::factory()->create();
        Order::factory()->pending()->count(2)->create(['user_id' => $user->id]);
        Order::factory()->paid()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/orders?status=pending');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.orders'));
    }

    public function test_can_paginate_orders(): void
    {
        $user = User::factory()->create();
        Order::factory()->count(20)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/orders?per_page=10');

        $response->assertStatus(200);
        $this->assertEquals(10, $response->json('meta.per_page'));
        $this->assertEquals(20, $response->json('meta.total'));
        $this->assertEquals(2, $response->json('meta.last_page'));
    }

    public function test_can_update_order_status(): void
    {
        $order = Order::factory()->pending()->create();

        $response = $this->patchJson("/api/v1/orders/{$order->id}/status", [
            'status' => 'paid',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('paid', $response->json('data.order.status'));
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid',
        ]);
    }

    public function test_validates_status_transitions(): void
    {
        $order = Order::factory()->completed()->create();

        $response = $this->patchJson("/api/v1/orders/{$order->id}/status", [
            'status' => 'pending',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['title' => 'Validation Error']);
    }

    public function test_cannot_transition_to_invalid_status(): void
    {
        $order = Order::factory()->pending()->create();

        $response = $this->patchJson("/api/v1/orders/{$order->id}/status", [
            'status' => 'invalid_status',
        ]);

        $response->assertStatus(422);
    }
}
