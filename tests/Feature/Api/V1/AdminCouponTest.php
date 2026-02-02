<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminCouponTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $accessToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin-coupon@example.com',
            'password' => Hash::make('Password123'),
            'status' => 'active',
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin-coupon@example.com',
            'password' => 'Password123',
        ]);

        $this->accessToken = $loginResponse->json('data.access_token');
    }

    public function test_create_percent_coupon(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->postJson('/api/v1/admin/coupons', [
                'code' => 'NEWCOUPON',
                'name' => 'New Coupon',
                'discount_type' => 'percent',
                'discount_value' => 15.00,
                'is_active' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'NEWCOUPON')
            ->assertJsonPath('data.discount_type', 'percent')
            ->assertJsonPath('data.discount_value', '15.00');

        $this->assertDatabaseHas('coupons', [
            'code' => 'NEWCOUPON',
            'discount_type' => 'percent',
        ]);
    }

    public function test_create_fixed_coupon(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->postJson('/api/v1/admin/coupons', [
                'code' => 'FIXED1000',
                'discount_type' => 'fixed',
                'discount_value' => 10.00,
                'currency' => 'PLN',
                'is_active' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'FIXED1000')
            ->assertJsonPath('data.discount_type', 'fixed')
            ->assertJsonPath('data.currency', 'PLN');
    }

    public function test_create_coupon_with_all_fields(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->postJson('/api/v1/admin/coupons', [
                'code' => 'FULLCOUPON',
                'name' => 'Full Featured Coupon',
                'discount_type' => 'percent',
                'discount_value' => 20.00,
                'starts_at' => '2026-03-01T00:00:00Z',
                'ends_at' => '2026-12-31T23:59:59Z',
                'min_subtotal' => 5000,
                'max_uses_total' => 100,
                'max_uses_per_customer' => 2,
                'is_active' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.min_subtotal', 5000)
            ->assertJsonPath('data.max_uses_total', 100)
            ->assertJsonPath('data.max_uses_per_customer', 2);
    }

    public function test_create_coupon_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/admin/coupons', [
            'code' => 'TEST',
            'discount_type' => 'percent',
            'discount_value' => 10.00,
        ]);

        $response->assertStatus(401);
    }

    public function test_create_coupon_validates_required_fields(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->postJson('/api/v1/admin/coupons', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'discount_type', 'discount_value']);
    }

    public function test_create_coupon_validates_unique_code(): void
    {
        Coupon::create([
            'code' => 'EXISTING',
            'discount_type' => 'percent',
            'discount_value' => 10.00,
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->postJson('/api/v1/admin/coupons', [
                'code' => 'EXISTING',
                'discount_type' => 'percent',
                'discount_value' => 15.00,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_create_fixed_coupon_requires_currency(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->postJson('/api/v1/admin/coupons', [
                'code' => 'FIXEDNOCURRENCY',
                'discount_type' => 'fixed',
                'discount_value' => 10.00,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['currency']);
    }

    public function test_list_coupons(): void
    {
        Coupon::create([
            'code' => 'COUPON1',
            'discount_type' => 'percent',
            'discount_value' => 10.00,
            'is_active' => true,
        ]);

        Coupon::create([
            'code' => 'COUPON2',
            'discount_type' => 'fixed',
            'discount_value' => 5.00,
            'currency' => 'PLN',
            'is_active' => false,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->getJson('/api/v1/admin/coupons');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'discount_type', 'discount_value'],
                ],
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    public function test_list_coupons_filter_by_active(): void
    {
        Coupon::create([
            'code' => 'ACTIVE1',
            'discount_type' => 'percent',
            'discount_value' => 10.00,
            'is_active' => true,
        ]);

        Coupon::create([
            'code' => 'INACTIVE1',
            'discount_type' => 'percent',
            'discount_value' => 10.00,
            'is_active' => false,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->getJson('/api/v1/admin/coupons?active=true');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'ACTIVE1');
    }

    public function test_list_coupons_filter_by_code(): void
    {
        Coupon::create([
            'code' => 'SUMMER20',
            'discount_type' => 'percent',
            'discount_value' => 20.00,
            'is_active' => true,
        ]);

        Coupon::create([
            'code' => 'WINTER10',
            'discount_type' => 'percent',
            'discount_value' => 10.00,
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->getJson('/api/v1/admin/coupons?code=SUMMER');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'SUMMER20');
    }

    public function test_show_coupon(): void
    {
        $coupon = Coupon::create([
            'code' => 'SHOWCOUPON',
            'discount_type' => 'percent',
            'discount_value' => 10.00,
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->getJson("/api/v1/admin/coupons/{$coupon->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $coupon->id)
            ->assertJsonPath('data.code', 'SHOWCOUPON');
    }

    public function test_show_coupon_not_found(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->getJson('/api/v1/admin/coupons/99999');

        $response->assertStatus(404);
    }

    public function test_update_coupon(): void
    {
        $coupon = Coupon::create([
            'code' => 'UPDATEME',
            'discount_type' => 'percent',
            'discount_value' => 10.00,
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->patchJson("/api/v1/admin/coupons/{$coupon->id}", [
                'discount_value' => 25.00,
                'is_active' => false,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.discount_value', '25.00')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('coupons', [
            'id' => $coupon->id,
            'discount_value' => 25.00,
            'is_active' => false,
        ]);
    }

    public function test_update_coupon_code(): void
    {
        $coupon = Coupon::create([
            'code' => 'OLDCODE',
            'discount_type' => 'percent',
            'discount_value' => 10.00,
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->patchJson("/api/v1/admin/coupons/{$coupon->id}", [
                'code' => 'NEWCODE',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.code', 'NEWCODE');
    }

    public function test_update_coupon_code_unique_validation(): void
    {
        Coupon::create([
            'code' => 'EXISTINGCODE',
            'discount_type' => 'percent',
            'discount_value' => 10.00,
            'is_active' => true,
        ]);

        $coupon = Coupon::create([
            'code' => 'MYCODE',
            'discount_type' => 'percent',
            'discount_value' => 10.00,
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->patchJson("/api/v1/admin/coupons/{$coupon->id}", [
                'code' => 'EXISTINGCODE',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_delete_coupon(): void
    {
        $coupon = Coupon::create([
            'code' => 'DELETEME',
            'discount_type' => 'percent',
            'discount_value' => 10.00,
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->deleteJson("/api/v1/admin/coupons/{$coupon->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('coupons', ['id' => $coupon->id]);
    }

    public function test_add_category_target_to_coupon(): void
    {
        $coupon = Coupon::create([
            'code' => 'TARGETED',
            'discount_type' => 'percent',
            'discount_value' => 10.00,
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Test Category',
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->postJson("/api/v1/admin/coupons/{$coupon->id}/targets", [
                'target_type' => 'category',
                'target_id' => $category->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.target_type', 'category')
            ->assertJsonPath('data.target_id', $category->id);

        $this->assertDatabaseHas('coupon_targets', [
            'coupon_id' => $coupon->id,
            'target_type' => 'category',
            'target_id' => $category->id,
        ]);
    }

    public function test_add_duplicate_target_returns_conflict(): void
    {
        $coupon = Coupon::create([
            'code' => 'TARGETED2',
            'discount_type' => 'percent',
            'discount_value' => 10.00,
            'is_active' => true,
        ]);

        CouponTarget::create([
            'coupon_id' => $coupon->id,
            'target_type' => 'category',
            'target_id' => 1,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->postJson("/api/v1/admin/coupons/{$coupon->id}/targets", [
                'target_type' => 'category',
                'target_id' => 1,
            ]);

        $response->assertStatus(409);
    }

    public function test_list_coupon_targets(): void
    {
        $coupon = Coupon::create([
            'code' => 'WITHTARGETS',
            'discount_type' => 'percent',
            'discount_value' => 10.00,
            'is_active' => true,
        ]);

        CouponTarget::create([
            'coupon_id' => $coupon->id,
            'target_type' => 'category',
            'target_id' => 1,
        ]);

        CouponTarget::create([
            'coupon_id' => $coupon->id,
            'target_type' => 'menu_item',
            'target_id' => 5,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->getJson("/api/v1/admin/coupons/{$coupon->id}/targets");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_remove_target_from_coupon(): void
    {
        $coupon = Coupon::create([
            'code' => 'REMOVETARGET',
            'discount_type' => 'percent',
            'discount_value' => 10.00,
            'is_active' => true,
        ]);

        $target = CouponTarget::create([
            'coupon_id' => $coupon->id,
            'target_type' => 'category',
            'target_id' => 1,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->deleteJson("/api/v1/admin/coupons/{$coupon->id}/targets/{$target->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('coupon_targets', ['id' => $target->id]);
    }

    public function test_remove_nonexistent_target_returns_404(): void
    {
        $coupon = Coupon::create([
            'code' => 'NOTARGET',
            'discount_type' => 'percent',
            'discount_value' => 10.00,
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->deleteJson("/api/v1/admin/coupons/{$coupon->id}/targets/99999");

        $response->assertStatus(404);
    }

    public function test_coupon_code_normalized_to_uppercase(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->postJson('/api/v1/admin/coupons', [
                'code' => 'lowercase',
                'discount_type' => 'percent',
                'discount_value' => 10.00,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'LOWERCASE');
    }
}
