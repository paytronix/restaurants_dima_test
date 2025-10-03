<?php

namespace Tests\Feature\Orders;

use App\Models\Cart;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_empty_cart(): void
    {
        $response = $this->getJson('/api/v1/cart');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'cart' => [
                    'id',
                    'items',
                    'totals',
                ],
            ],
            'meta' => ['trace_id'],
        ]);
        $this->assertCount(0, $response->json('data.cart.items'));
    }

    public function test_can_add_item_to_cart(): void
    {
        $menuItem = MenuItem::factory()->available()->create([
            'price' => 10.00,
        ]);

        $response = $this->postJson('/api/v1/cart/items', [
            'menu_item_id' => $menuItem->id,
            'quantity' => 2,
            'special_instructions' => 'No onions',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'cart_item' => [
                    'id',
                    'menu_item',
                    'quantity',
                    'price_snapshot',
                    'special_instructions',
                ],
            ],
            'meta' => ['trace_id'],
        ]);
        $this->assertEquals(2, $response->json('data.cart_item.quantity'));
        $this->assertEquals('No onions', $response->json('data.cart_item.special_instructions'));
    }

    public function test_cannot_add_unavailable_menu_item(): void
    {
        $menuItem = MenuItem::factory()->unavailable()->create();

        $response = $this->postJson('/api/v1/cart/items', [
            'menu_item_id' => $menuItem->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['title' => 'Validation Error']);
    }

    public function test_validates_quantity_range(): void
    {
        $menuItem = MenuItem::factory()->available()->create();

        $response = $this->postJson('/api/v1/cart/items', [
            'menu_item_id' => $menuItem->id,
            'quantity' => 0,
        ]);

        $response->assertStatus(422);

        $response = $this->postJson('/api/v1/cart/items', [
            'menu_item_id' => $menuItem->id,
            'quantity' => 100,
        ]);

        $response->assertStatus(422);
    }

    public function test_can_update_cart_item_quantity(): void
    {
        $menuItem = MenuItem::factory()->available()->create();
        $cart = Cart::factory()->create();
        $cartItem = $cart->items()->create([
            'menu_item_id' => $menuItem->id,
            'quantity' => 1,
            'price_snapshot' => $menuItem->price,
        ]);

        $response = $this->patchJson("/api/v1/cart/items/{$cartItem->id}", [
            'quantity' => 3,
        ]);

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('data.cart_item.quantity'));
    }

    public function test_can_update_cart_item_special_instructions(): void
    {
        $menuItem = MenuItem::factory()->available()->create();
        $cart = Cart::factory()->create();
        $cartItem = $cart->items()->create([
            'menu_item_id' => $menuItem->id,
            'quantity' => 1,
            'price_snapshot' => $menuItem->price,
        ]);

        $response = $this->patchJson("/api/v1/cart/items/{$cartItem->id}", [
            'special_instructions' => 'Extra cheese',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Extra cheese', $response->json('data.cart_item.special_instructions'));
    }

    public function test_can_remove_cart_item(): void
    {
        $menuItem = MenuItem::factory()->available()->create();
        $cart = Cart::factory()->create();
        $cartItem = $cart->items()->create([
            'menu_item_id' => $menuItem->id,
            'quantity' => 1,
            'price_snapshot' => $menuItem->price,
        ]);

        $response = $this->deleteJson("/api/v1/cart/items/{$cartItem->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('cart_items', ['id' => $cartItem->id]);
    }

    public function test_cart_calculates_totals_correctly(): void
    {
        $menuItem1 = MenuItem::factory()->available()->create(['price' => 10.00]);
        $menuItem2 = MenuItem::factory()->available()->create(['price' => 15.00]);

        $this->postJson('/api/v1/cart/items', [
            'menu_item_id' => $menuItem1->id,
            'quantity' => 2,
        ]);

        $this->postJson('/api/v1/cart/items', [
            'menu_item_id' => $menuItem2->id,
            'quantity' => 1,
        ]);

        $response = $this->getJson('/api/v1/cart');

        $subtotal = (10.00 * 2) + (15.00 * 1);
        $tax = $subtotal * 0.08;
        $total = $subtotal + $tax;

        $response->assertStatus(200);
        $this->assertEquals($subtotal, $response->json('data.cart.totals.subtotal'));
        $this->assertEquals(round($tax, 2), $response->json('data.cart.totals.tax'));
        $this->assertEquals(round($total, 2), $response->json('data.cart.totals.total'));
    }
}
