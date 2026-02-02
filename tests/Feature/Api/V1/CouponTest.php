<?php

namespace Tests\Feature\Api\V1;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $accessToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'coupon-test@example.com',
            'password' => Hash::make('Password123'),
            'status' => 'active',
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'coupon-test@example.com',
            'password' => 'Password123',
        ]);

        $this->accessToken = $loginResponse->json('data.access_token');
    }

    public function test_apply_valid_percent_coupon(): void
    {
        $coupon = Coupon::create([
            'code' => 'PERCENT10',
            'discount_type' => 'percent',
            'discount_value' => 10.00,
            'is_active' => true,
        ]);

        $order = Order::create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_DRAFT,
            'subtotal' => 10000,
            'total' => 10000,
            'currency' => 'PLN',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->postJson("/api/v1/orders/{$order->id}/coupon", [
                'code' => 'PERCENT10',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.order.discount_total', 1000)
            ->assertJsonPath('data.order.total', 9000)
            ->assertJsonPath('data.order.coupon.code', 'PERCENT10');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'coupon_id' => $coupon->id,
            'discount_total' => 1000,
            'total' => 9000,
        ]);
    }

    public function test_apply_valid_fixed_coupon(): void
    {
        $coupon = Coupon::create([
            'code' => 'FIXED500',
            'discount_type' => 'fixed',
            'discount_value' => 5.00,
            'currency' => 'PLN',
            'is_active' => true,
        ]);

        $order = Order::create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_DRAFT,
            'subtotal' => 10000,
            'total' => 10000,
            'currency' => 'PLN',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->postJson("/api/v1/orders/{$order->id}/coupon", [
                'code' => 'FIXED500',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.order.discount_total', 500)
            ->assertJsonPath('data.order.total', 9500);
    }

    public function test_apply_coupon_requires_authentication(): void
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_DRAFT,
            'subtotal' => 10000,
            'total' => 10000,
            'currency' => 'PLN',
        ]);

        $response = $this->postJson("/api/v1/orders/{$order->id}/coupon", [
            'code' => 'TEST',
        ]);

        $response->assertStatus(401);
    }

    public function test_apply_coupon_fails_for_non_draft_order(): void
    {
        Coupon::create([
            'code' => 'VALID',
            'discount_type' => 'percent',
            'discount_value' => 10.00,
            'is_active' => true,
        ]);

        $order = Order::create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_PAID,
            'subtotal' => 10000,
            'total' => 10000,
            'currency' => 'PLN',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->postJson("/api/v1/orders/{$order->id}/coupon", [
                'code' => 'VALID',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('detail', 'Coupon can only be applied to draft orders');
    }

    public function test_apply_invalid_coupon_code(): void
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_DRAFT,
            'subtotal' => 10000,
            'total' => 10000,
            'currency' => 'PLN',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->postJson("/api/v1/orders/{$order->id}/coupon", [
                'code' => 'INVALID',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('detail', 'This coupon code is not valid');
    }

    public function test_apply_inactive_coupon(): void
    {
        Coupon::create([
            'code' => 'INACTIVE',
            'discount_type' => 'percent',
            'discount_value' => 10.00,
            'is_active' => false,
        ]);

        $order = Order::create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_DRAFT,
            'subtotal' => 10000,
            'total' => 10000,
            'currency' => 'PLN',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->postJson("/api/v1/orders/{$order->id}/coupon", [
                'code' => 'INACTIVE',
            ]);

        $response->assertStatus(422);
    }

    public function test_apply_coupon_with_min_subtotal_requirement(): void
    {
        Coupon::create([
            'code' => 'MINORDER',
            'discount_type' => 'percent',
            'discount_value' => 10.00,
            'min_subtotal' => 5000,
            'is_active' => true,
        ]);

        $order = Order::create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_DRAFT,
            'subtotal' => 3000,
            'total' => 3000,
            'currency' => 'PLN',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->postJson("/api/v1/orders/{$order->id}/coupon", [
                'code' => 'MINORDER',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('detail', 'Minimum order subtotal of 50.00 PLN required');
    }

    public function test_apply_coupon_with_min_subtotal_met(): void
    {
        Coupon::create([
            'code' => 'MINORDER',
            'discount_type' => 'percent',
            'discount_value' => 10.00,
            'min_subtotal' => 5000,
            'is_active' => true,
        ]);

        $order = Order::create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_DRAFT,
            'subtotal' => 6000,
            'total' => 6000,
            'currency' => 'PLN',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->postJson("/api/v1/orders/{$order->id}/coupon", [
                'code' => 'MINORDER',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.order.discount_total', 600);
    }

    public function test_apply_coupon_outside_date_window_before_start(): void
    {
        Coupon::create([
            'code' => 'FUTURE',
            'discount_type' => 'percent',
            'discount_value' => 10.00,
            'starts_at' => now()->addDays(7),
            'is_active' => true,
        ]);

        $order = Order::create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_DRAFT,
            'subtotal' => 10000,
            'total' => 10000,
            'currency' => 'PLN',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->postJson("/api/v1/orders/{$order->id}/coupon", [
                'code' => 'FUTURE',
            ]);

        $response->assertStatus(422);
    }

    public function test_apply_coupon_outside_date_window_after_end(): void
    {
        Coupon::create([
            'code' => 'EXPIRED',
            'discount_type' => 'percent',
            'discount_value' => 10.00,
            'ends_at' => now()->subDays(1),
            'is_active' => true,
        ]);

        $order = Order::create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_DRAFT,
            'subtotal' => 10000,
            'total' => 10000,
            'currency' => 'PLN',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->postJson("/api/v1/orders/{$order->id}/coupon", [
                'code' => 'EXPIRED',
            ]);

        $response->assertStatus(422);
    }

    public function test_apply_coupon_within_date_window(): void
    {
        Coupon::create([
            'code' => 'CURRENT',
            'discount_type' => 'percent',
            'discount_value' => 10.00,
            'starts_at' => now()->subDays(1),
            'ends_at' => now()->addDays(7),
            'is_active' => true,
        ]);

        $order = Order::create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_DRAFT,
            'subtotal' => 10000,
            'total' => 10000,
            'currency' => 'PLN',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->postJson("/api/v1/orders/{$order->id}/coupon", [
                'code' => 'CURRENT',
            ]);

        $response->assertStatus(200);
    }

    public function test_apply_coupon_global_usage_limit_reached(): void
    {
        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other-user@example.com',
            'password' => Hash::make('Password123'),
            'status' => 'active',
        ]);

        $coupon = Coupon::create([
            'code' => 'LIMITED',
            'discount_type' => 'percent',
            'discount_value' => 10.00,
            'max_uses_total' => 1,
            'is_active' => true,
        ]);

        CouponRedemption::create([
            'coupon_id' => $coupon->id,
            'user_id' => $otherUser->id,
            'status' => CouponRedemption::STATUS_REDEEMED,
            'reserved_at' => now(),
            'redeemed_at' => now(),
        ]);

        $order = Order::create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_DRAFT,
            'subtotal' => 10000,
            'total' => 10000,
            'currency' => 'PLN',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->postJson("/api/v1/orders/{$order->id}/coupon", [
                'code' => 'LIMITED',
            ]);

        $response->assertStatus(422);
    }

    public function test_apply_coupon_per_customer_limit_reached(): void
    {
        $coupon = Coupon::create([
            'code' => 'ONCEPERUSER',
            'discount_type' => 'percent',
            'discount_value' => 10.00,
            'max_uses_per_customer' => 1,
            'is_active' => true,
        ]);

        CouponRedemption::create([
            'coupon_id' => $coupon->id,
            'user_id' => $this->user->id,
            'status' => CouponRedemption::STATUS_REDEEMED,
            'reserved_at' => now(),
            'redeemed_at' => now(),
        ]);

        $order = Order::create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_DRAFT,
            'subtotal' => 10000,
            'total' => 10000,
            'currency' => 'PLN',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->postJson("/api/v1/orders/{$order->id}/coupon", [
                'code' => 'ONCEPERUSER',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('detail', 'You have already used this coupon');
    }

    public function test_replace_coupon_behavior(): void
    {
        $coupon1 = Coupon::create([
            'code' => 'FIRST10',
            'discount_type' => 'percent',
            'discount_value' => 10.00,
            'is_active' => true,
        ]);

        $coupon2 = Coupon::create([
            'code' => 'SECOND20',
            'discount_type' => 'percent',
            'discount_value' => 20.00,
            'is_active' => true,
        ]);

        $order = Order::create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_DRAFT,
            'subtotal' => 10000,
            'total' => 10000,
            'currency' => 'PLN',
        ]);

        $response1 = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->postJson("/api/v1/orders/{$order->id}/coupon", [
                'code' => 'FIRST10',
            ]);

        $response1->assertStatus(200)
            ->assertJsonPath('data.order.discount_total', 1000);

        $response2 = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->postJson("/api/v1/orders/{$order->id}/coupon", [
                'code' => 'SECOND20',
            ]);

        $response2->assertStatus(200)
            ->assertJsonPath('data.order.discount_total', 2000)
            ->assertJsonPath('data.order.coupon.code', 'SECOND20');

        $order->refresh();
        $this->assertEquals($coupon2->id, $order->coupon_id);

        $releasedRedemption = CouponRedemption::where('coupon_id', $coupon1->id)
            ->where('order_id', $order->id)
            ->first();
        $this->assertEquals(CouponRedemption::STATUS_RELEASED, $releasedRedemption->status);
    }

    public function test_remove_coupon(): void
    {
        $coupon = Coupon::create([
            'code' => 'TOREMOVE',
            'discount_type' => 'percent',
            'discount_value' => 10.00,
            'is_active' => true,
        ]);

        $order = Order::create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_DRAFT,
            'subtotal' => 10000,
            'total' => 10000,
            'currency' => 'PLN',
        ]);

        $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->postJson("/api/v1/orders/{$order->id}/coupon", [
                'code' => 'TOREMOVE',
            ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->deleteJson("/api/v1/orders/{$order->id}/coupon");

        $response->assertStatus(200)
            ->assertJsonPath('data.order.discount_total', 0)
            ->assertJsonPath('data.order.total', 10000)
            ->assertJsonPath('data.order.coupon', null);

        $order->refresh();
        $this->assertNull($order->coupon_id);
    }

    public function test_remove_coupon_idempotent(): void
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_DRAFT,
            'subtotal' => 10000,
            'total' => 10000,
            'currency' => 'PLN',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->deleteJson("/api/v1/orders/{$order->id}/coupon");

        $response->assertStatus(200)
            ->assertJsonPath('data.order.coupon', null);
    }

    public function test_rate_limiting_on_invalid_attempts(): void
    {
        config(['promotions.invalid_attempt_limit' => 3]);
        config(['promotions.invalid_attempt_window' => 60]);

        $order = Order::create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_DRAFT,
            'subtotal' => 10000,
            'total' => 10000,
            'currency' => 'PLN',
        ]);

        for ($i = 0; $i < 3; $i++) {
            $this->withHeader('Authorization', "Bearer {$this->accessToken}")
                ->postJson("/api/v1/orders/{$order->id}/coupon", [
                    'code' => 'INVALID'.$i,
                ]);
        }

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->postJson("/api/v1/orders/{$order->id}/coupon", [
                'code' => 'ANOTHERINVALID',
            ]);

        $response->assertStatus(429)
            ->assertJsonPath('title', 'Too Many Requests');
    }

    public function test_coupon_creates_reservation(): void
    {
        $coupon = Coupon::create([
            'code' => 'RESERVE',
            'discount_type' => 'percent',
            'discount_value' => 10.00,
            'is_active' => true,
        ]);

        $order = Order::create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_DRAFT,
            'subtotal' => 10000,
            'total' => 10000,
            'currency' => 'PLN',
        ]);

        $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->postJson("/api/v1/orders/{$order->id}/coupon", [
                'code' => 'RESERVE',
            ]);

        $this->assertDatabaseHas('coupon_redemptions', [
            'coupon_id' => $coupon->id,
            'order_id' => $order->id,
            'user_id' => $this->user->id,
            'status' => CouponRedemption::STATUS_RESERVED,
        ]);
    }

    public function test_coupon_code_case_insensitive(): void
    {
        Coupon::create([
            'code' => 'UPPERCASE',
            'discount_type' => 'percent',
            'discount_value' => 10.00,
            'is_active' => true,
        ]);

        $order = Order::create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_DRAFT,
            'subtotal' => 10000,
            'total' => 10000,
            'currency' => 'PLN',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->postJson("/api/v1/orders/{$order->id}/coupon", [
                'code' => 'uppercase',
            ]);

        $response->assertStatus(200);
    }

    public function test_fixed_discount_currency_mismatch(): void
    {
        Coupon::create([
            'code' => 'FIXEDUSD',
            'discount_type' => 'fixed',
            'discount_value' => 5.00,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $order = Order::create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_DRAFT,
            'subtotal' => 10000,
            'total' => 10000,
            'currency' => 'PLN',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->postJson("/api/v1/orders/{$order->id}/coupon", [
                'code' => 'FIXEDUSD',
            ]);

        $response->assertStatus(422);
    }
}
