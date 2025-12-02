<?php

namespace Tests\Feature\Api\V1;

use App\Models\EmailVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_request_verification_email(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('Password123'),
            'status' => 'active',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/email/verify/request');

        $response->assertStatus(200)
            ->assertJsonPath('data.message', 'Verification email sent.');

        $this->assertDatabaseHas('email_verifications', [
            'user_id' => $user->id,
        ]);
    }

    public function test_verification_request_fails_for_already_verified_user(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('Password123'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/email/verify/request');

        $response->assertStatus(400);
    }

    public function test_verification_request_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/auth/email/verify/request');

        $response->assertStatus(401);
    }

    public function test_user_can_verify_email_with_valid_token(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('Password123'),
            'status' => 'active',
        ]);

        $verificationToken = str_repeat('a', 64);

        EmailVerification::create([
            'user_id' => $user->id,
            'token' => $verificationToken,
            'expires_at' => now()->addDay(),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/email/verify/confirm', [
            'token' => $verificationToken,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.message', 'Email verified successfully.');

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_verification_fails_with_invalid_token(): void
    {
        $response = $this->postJson('/api/v1/auth/email/verify/confirm', [
            'token' => str_repeat('x', 64),
        ]);

        $response->assertStatus(400);
    }

    public function test_verification_fails_with_expired_token(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('Password123'),
            'status' => 'active',
        ]);

        $verificationToken = str_repeat('b', 64);

        EmailVerification::create([
            'user_id' => $user->id,
            'token' => $verificationToken,
            'expires_at' => now()->subHour(),
            'created_at' => now()->subDay(),
        ]);

        $response = $this->postJson('/api/v1/auth/email/verify/confirm', [
            'token' => $verificationToken,
        ]);

        $response->assertStatus(400);
    }

    public function test_verification_fails_with_already_consumed_token(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('Password123'),
            'status' => 'active',
        ]);

        $verificationToken = str_repeat('c', 64);

        EmailVerification::create([
            'user_id' => $user->id,
            'token' => $verificationToken,
            'expires_at' => now()->addDay(),
            'consumed_at' => now(),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/email/verify/confirm', [
            'token' => $verificationToken,
        ]);

        $response->assertStatus(400);
    }
}
