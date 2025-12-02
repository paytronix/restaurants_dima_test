<?php

namespace Tests\Feature\Api\V1;

use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AddressTest extends TestCase
{
    use RefreshDatabase;

    private function createAuthenticatedUser(): array
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('Password123'),
            'status' => 'active',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        return [$user, $token];
    }

    public function test_authenticated_user_can_list_addresses(): void
    {
        [$user, $token] = $this->createAuthenticatedUser();

        CustomerAddress::create([
            'user_id' => $user->id,
            'label' => 'Home',
            'country' => 'US',
            'city' => 'New York',
            'postal_code' => '10001',
            'street_line1' => '123 Main St',
            'is_default' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me/addresses');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'label',
                        'country',
                        'city',
                        'postal_code',
                        'street_line1',
                        'street_line2',
                        'is_default',
                    ],
                ],
                'meta' => ['total'],
            ])
            ->assertJsonPath('meta.total', 1);
    }

    public function test_list_addresses_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/me/addresses');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_create_address(): void
    {
        [$user, $token] = $this->createAuthenticatedUser();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/me/addresses', [
                'label' => 'Home',
                'country' => 'US',
                'city' => 'New York',
                'postal_code' => '10001',
                'street_line1' => '123 Main St',
                'street_line2' => 'Apt 4B',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.label', 'Home')
            ->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('customer_addresses', [
            'user_id' => $user->id,
            'label' => 'Home',
        ]);
    }

    public function test_first_address_is_automatically_default(): void
    {
        [$user, $token] = $this->createAuthenticatedUser();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/me/addresses', [
                'label' => 'Home',
                'country' => 'US',
                'city' => 'New York',
                'postal_code' => '10001',
                'street_line1' => '123 Main St',
                'is_default' => false,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_default', true);
    }

    public function test_setting_new_default_unsets_previous_default(): void
    {
        [$user, $token] = $this->createAuthenticatedUser();

        $firstAddress = CustomerAddress::create([
            'user_id' => $user->id,
            'label' => 'Home',
            'country' => 'US',
            'city' => 'New York',
            'postal_code' => '10001',
            'street_line1' => '123 Main St',
            'is_default' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/me/addresses', [
                'label' => 'Work',
                'country' => 'US',
                'city' => 'New York',
                'postal_code' => '10002',
                'street_line1' => '456 Office Blvd',
                'is_default' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_default', true);

        $firstAddress->refresh();
        $this->assertFalse($firstAddress->is_default);
    }

    public function test_address_creation_validates_required_fields(): void
    {
        [$user, $token] = $this->createAuthenticatedUser();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/me/addresses', [
                'label' => 'Home',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['country', 'city', 'postal_code', 'street_line1']);
    }

    public function test_address_creation_validates_country_code_length(): void
    {
        [$user, $token] = $this->createAuthenticatedUser();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/me/addresses', [
                'label' => 'Home',
                'country' => 'USA',
                'city' => 'New York',
                'postal_code' => '10001',
                'street_line1' => '123 Main St',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['country']);
    }

    public function test_authenticated_user_can_update_own_address(): void
    {
        [$user, $token] = $this->createAuthenticatedUser();

        $address = CustomerAddress::create([
            'user_id' => $user->id,
            'label' => 'Home',
            'country' => 'US',
            'city' => 'New York',
            'postal_code' => '10001',
            'street_line1' => '123 Main St',
            'is_default' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson("/api/v1/me/addresses/{$address->id}", [
                'label' => 'Updated Home',
                'city' => 'Los Angeles',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.label', 'Updated Home')
            ->assertJsonPath('data.city', 'Los Angeles');
    }

    public function test_user_cannot_update_other_users_address(): void
    {
        [$user, $token] = $this->createAuthenticatedUser();

        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => Hash::make('Password123'),
            'status' => 'active',
        ]);

        $otherAddress = CustomerAddress::create([
            'user_id' => $otherUser->id,
            'label' => 'Other Home',
            'country' => 'US',
            'city' => 'Chicago',
            'postal_code' => '60601',
            'street_line1' => '789 Other St',
            'is_default' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson("/api/v1/me/addresses/{$otherAddress->id}", [
                'label' => 'Hacked',
            ]);

        $response->assertStatus(403);
    }

    public function test_authenticated_user_can_delete_own_address(): void
    {
        [$user, $token] = $this->createAuthenticatedUser();

        $address = CustomerAddress::create([
            'user_id' => $user->id,
            'label' => 'Home',
            'country' => 'US',
            'city' => 'New York',
            'postal_code' => '10001',
            'street_line1' => '123 Main St',
            'is_default' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson("/api/v1/me/addresses/{$address->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('customer_addresses', [
            'id' => $address->id,
        ]);
    }

    public function test_deleting_default_address_promotes_next_address(): void
    {
        [$user, $token] = $this->createAuthenticatedUser();

        $firstAddress = CustomerAddress::create([
            'user_id' => $user->id,
            'label' => 'Home',
            'country' => 'US',
            'city' => 'New York',
            'postal_code' => '10001',
            'street_line1' => '123 Main St',
            'is_default' => true,
        ]);

        $secondAddress = CustomerAddress::create([
            'user_id' => $user->id,
            'label' => 'Work',
            'country' => 'US',
            'city' => 'New York',
            'postal_code' => '10002',
            'street_line1' => '456 Office Blvd',
            'is_default' => false,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson("/api/v1/me/addresses/{$firstAddress->id}");

        $secondAddress->refresh();
        $this->assertTrue($secondAddress->is_default);
    }

    public function test_user_cannot_delete_other_users_address(): void
    {
        [$user, $token] = $this->createAuthenticatedUser();

        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => Hash::make('Password123'),
            'status' => 'active',
        ]);

        $otherAddress = CustomerAddress::create([
            'user_id' => $otherUser->id,
            'label' => 'Other Home',
            'country' => 'US',
            'city' => 'Chicago',
            'postal_code' => '60601',
            'street_line1' => '789 Other St',
            'is_default' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson("/api/v1/me/addresses/{$otherAddress->id}");

        $response->assertStatus(403);
    }

    public function test_authenticated_user_can_set_address_as_default(): void
    {
        [$user, $token] = $this->createAuthenticatedUser();

        $firstAddress = CustomerAddress::create([
            'user_id' => $user->id,
            'label' => 'Home',
            'country' => 'US',
            'city' => 'New York',
            'postal_code' => '10001',
            'street_line1' => '123 Main St',
            'is_default' => true,
        ]);

        $secondAddress = CustomerAddress::create([
            'user_id' => $user->id,
            'label' => 'Work',
            'country' => 'US',
            'city' => 'New York',
            'postal_code' => '10002',
            'street_line1' => '456 Office Blvd',
            'is_default' => false,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/me/addresses/{$secondAddress->id}/make-default");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_default', true);

        $firstAddress->refresh();
        $this->assertFalse($firstAddress->is_default);
    }

    public function test_update_nonexistent_address_returns_404(): void
    {
        [$user, $token] = $this->createAuthenticatedUser();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/me/addresses/99999', [
                'label' => 'Updated',
            ]);

        $response->assertStatus(404);
    }

    public function test_delete_nonexistent_address_returns_404(): void
    {
        [$user, $token] = $this->createAuthenticatedUser();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/me/addresses/99999');

        $response->assertStatus(404);
    }
}
