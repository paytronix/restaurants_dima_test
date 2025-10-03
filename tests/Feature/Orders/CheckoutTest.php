<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Models\Cart;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_checkout_with_valid_cart(): void
    {
        $menuItem = MenuItem::factory()->available()->create(['price' => 10.00]);
        $cart = Cart::factory()->create([
            'user_id' => null,
            'session_id' => session()->getId(),
        ]);
        $cart->items()->create([
            'menu_item_id' => $menuItem->id,
            'quantity' => 2,
            'price_snapshot' => $menuItem->price,
        ]);

        $response = $this->postJson('/api/v1/checkout', [
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'customer_phone' => '555-1234',
            'special_instructions' => 'Ring doorbell',
        ]);

        $response->assertStatus(201);
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
                ],
            ],
            'meta' => ['trace_id'],
        ]);

        $this->assertEquals('John Doe', $response->json('data.order.customer_name'));
        $this->assertEquals('pending', $response->json('data.order.status'));
        $this->assertEquals(20.00, $response->json('data.order.subtotal'));
    }

    public function test_cannot_checkout_with_empty_cart(): void
    {
        $response = $this->postJson('/api/v1/checkout', [
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'customer_phone' => '555-1234',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['detail' => 'Cart is empty']);
    }

    public function test_validates_customer_information(): void
    {
        $menuItem = MenuItem::factory()->available()->create();
        $cart = Cart::factory()->create([
            'user_id' => null,
            'session_id' => session()->getId(),
        ]);
        $cart->items()->create([
            'menu_item_id' => $menuItem->id,
            'quantity' => 1,
            'price_snapshot' => $menuItem->price,
        ]);

        $response = $this->postJson('/api/v1/checkout', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['customer_name', 'customer_email', 'customer_phone']);
    }

    public function test_validates_email_format(): void
    {
        $menuItem = MenuItem::factory()->available()->create();
        $cart = Cart::factory()->create([
            'user_id' => null,
            'session_id' => session()->getId(),
        ]);
        $cart->items()->create([
            'menu_item_id' => $menuItem->id,
            'quantity' => 1,
            'price_snapshot' => $menuItem->price,
        ]);

        $response = $this->postJson('/api/v1/checkout', [
            'customer_name' => 'John Doe',
            'customer_email' => 'invalid-email',
            'customer_phone' => '555-1234',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['customer_email']);
    }

    public function test_cart_is_cleared_after_successful_checkout(): void
    {
        $menuItem = MenuItem::factory()->available()->create();
        $cart = Cart::factory()->create([
            'user_id' => null,
            'session_id' => session()->getId(),
        ]);
        $cartItem = $cart->items()->create([
            'menu_item_id' => $menuItem->id,
            'quantity' => 1,
            'price_snapshot' => $menuItem->price,
        ]);

        $this->postJson('/api/v1/checkout', [
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'customer_phone' => '555-1234',
        ]);

        $this->assertDatabaseMissing('carts', ['id' => $cart->id]);
        $this->assertDatabaseMissing('cart_items', ['id' => $cartItem->id]);
    }

    public function test_order_items_are_created_from_cart(): void
    {
        $menuItem1 = MenuItem::factory()->available()->create(['name' => 'Burger']);
        $menuItem2 = MenuItem::factory()->available()->create(['name' => 'Fries']);
        $cart = Cart::factory()->create([
            'user_id' => null,
            'session_id' => session()->getId(),
        ]);
        $cart->items()->create([
            'menu_item_id' => $menuItem1->id,
            'quantity' => 2,
            'price_snapshot' => $menuItem1->price,
        ]);
        $cart->items()->create([
            'menu_item_id' => $menuItem2->id,
            'quantity' => 1,
            'price_snapshot' => $menuItem2->price,
        ]);

        $response = $this->postJson('/api/v1/checkout', [
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'customer_phone' => '555-1234',
        ]);

        $response->assertStatus(201);
        $this->assertCount(2, $response->json('data.order.items'));

        $orderId = $response->json('data.order.id');
        $this->assertDatabaseCount('order_items', 2);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'menu_item_id' => $menuItem1->id,
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'menu_item_id' => $menuItem2->id,
            'quantity' => 1,
        ]);
    }
}
