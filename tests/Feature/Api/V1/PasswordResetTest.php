<?php

namespace Tests\Feature\Api\V1;

use App\Models\PasswordReset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_password_reset(): void
    {
        User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('Password123'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/password/forgot', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.message', 'If the email exists, a reset link has been sent.');

        $this->assertDatabaseHas('custom_password_resets', [
            'email' => 'test@example.com',
        ]);
    }

    public function test_password_reset_request_returns_success_for_nonexistent_email(): void
    {
        $response = $this->postJson('/api/v1/auth/password/forgot', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.message', 'If the email exists, a reset link has been sent.');
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('OldPassword123'),
            'status' => 'active',
        ]);

        $resetToken = str_repeat('d', 64);

        PasswordReset::create([
            'email' => 'test@example.com',
            'token' => $resetToken,
            'expires_at' => now()->addHour(),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'token' => $resetToken,
            'email' => 'test@example.com',
            'password' => 'NewPassword456',
            'password_confirmation' => 'NewPassword456',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.message', 'Password reset successfully.');

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword456', $user->password));
    }

    public function test_password_reset_fails_with_invalid_token(): void
    {
        User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('Password123'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'token' => str_repeat('x', 64),
            'email' => 'test@example.com',
            'password' => 'NewPassword456',
            'password_confirmation' => 'NewPassword456',
        ]);

        $response->assertStatus(400);
    }

    public function test_password_reset_fails_with_expired_token(): void
    {
        User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('Password123'),
            'status' => 'active',
        ]);

        $resetToken = str_repeat('e', 64);

        PasswordReset::create([
            'email' => 'test@example.com',
            'token' => $resetToken,
            'expires_at' => now()->subHour(),
            'created_at' => now()->subDay(),
        ]);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'token' => $resetToken,
            'email' => 'test@example.com',
            'password' => 'NewPassword456',
            'password_confirmation' => 'NewPassword456',
        ]);

        $response->assertStatus(400);
    }

    public function test_password_reset_fails_with_wrong_email(): void
    {
        User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('Password123'),
            'status' => 'active',
        ]);

        $resetToken = str_repeat('f', 64);

        PasswordReset::create([
            'email' => 'test@example.com',
            'token' => $resetToken,
            'expires_at' => now()->addHour(),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'token' => $resetToken,
            'email' => 'wrong@example.com',
            'password' => 'NewPassword456',
            'password_confirmation' => 'NewPassword456',
        ]);

        $response->assertStatus(400);
    }

    public function test_password_reset_revokes_all_tokens(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('OldPassword123'),
            'status' => 'active',
        ]);

        $accessToken = $user->createToken('test')->plainTextToken;

        $resetToken = str_repeat('g', 64);

        PasswordReset::create([
            'email' => 'test@example.com',
            'token' => $resetToken,
            'expires_at' => now()->addHour(),
            'created_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/password/reset', [
            'token' => $resetToken,
            'email' => 'test@example.com',
            'password' => 'NewPassword456',
            'password_confirmation' => 'NewPassword456',
        ]);

        $meResponse = $this->withHeader('Authorization', 'Bearer '.$accessToken)
            ->getJson('/api/v1/me');

        $meResponse->assertStatus(401);
    }
}
