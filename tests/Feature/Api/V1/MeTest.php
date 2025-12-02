<?php

namespace Tests\Feature\Api\V1;

use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MeTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('Password123'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        CustomerProfile::create([
            'user_id' => $user->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+1234567890',
            'marketing_opt_in' => true,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'email',
                    'email_verified',
                    'profile' => [
                        'first_name',
                        'last_name',
                        'phone',
                        'marketing_opt_in',
                        'birth_date',
                    ],
                ],
                'meta',
            ])
            ->assertJsonPath('data.email', 'test@example.com')
            ->assertJsonPath('data.email_verified', true)
            ->assertJsonPath('data.profile.first_name', 'John');
    }

    public function test_get_profile_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/me');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_update_profile(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('Password123'),
            'status' => 'active',
        ]);

        CustomerProfile::create([
            'user_id' => $user->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/me', [
                'first_name' => 'Jane',
                'phone' => '+9876543210',
                'marketing_opt_in' => true,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.profile.first_name', 'Jane')
            ->assertJsonPath('data.profile.phone', '+9876543210')
            ->assertJsonPath('data.profile.marketing_opt_in', true);
    }

    public function test_profile_update_validates_phone_format(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('Password123'),
            'status' => 'active',
        ]);

        CustomerProfile::create([
            'user_id' => $user->id,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/me', [
                'phone' => 'invalid-phone!@#',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_profile_update_validates_birth_date(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('Password123'),
            'status' => 'active',
        ]);

        CustomerProfile::create([
            'user_id' => $user->id,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/me', [
                'birth_date' => now()->addYear()->format('Y-m-d'),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['birth_date']);
    }

    public function test_profile_creates_automatically_if_not_exists(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('Password123'),
            'status' => 'active',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.profile.first_name', null);

        $this->assertDatabaseHas('customer_profiles', [
            'user_id' => $user->id,
        ]);
    }
}
