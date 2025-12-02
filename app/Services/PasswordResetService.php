<?php

namespace App\Services;

use App\Models\PasswordReset;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PasswordResetService
{
    public function sendResetEmail(string $email): void
    {
        $user = User::where('email', $email)->first();

        if ($user === null) {
            Log::info('Password reset requested for non-existent email', [
                'email' => $email,
            ]);

            return;
        }

        PasswordReset::where('email', $email)
            ->where('consumed_at', null)
            ->update(['consumed_at' => now()]);

        $token = Str::random(64);

        PasswordReset::create([
            'email' => $email,
            'token' => $token,
            'expires_at' => now()->addSeconds($this->getResetTtl()),
            'created_at' => now(),
        ]);

        Log::info('Password reset requested', [
            'user_id' => $user->id,
        ]);
    }

    public function reset(string $token, string $email, string $password): bool
    {
        $resetRecord = PasswordReset::where('token', $token)
            ->where('email', $email)
            ->first();

        if ($resetRecord === null) {
            Log::warning('Password reset failed: token not found', [
                'email' => $email,
            ]);

            return false;
        }

        if (! $resetRecord->isValid()) {
            Log::warning('Password reset failed: token invalid', [
                'reset_id' => $resetRecord->id,
                'expired' => $resetRecord->isExpired(),
                'consumed' => $resetRecord->isConsumed(),
            ]);

            return false;
        }

        $user = User::where('email', $email)->first();

        if ($user === null) {
            Log::warning('Password reset failed: user not found', [
                'email' => $email,
            ]);

            return false;
        }

        $resetRecord->update(['consumed_at' => now()]);

        $user->update([
            'password' => Hash::make($password),
        ]);

        $user->tokens()->delete();
        $user->refreshTokens()->update(['revoked' => true]);

        Log::info('Password reset successful', [
            'user_id' => $user->id,
        ]);

        return true;
    }

    private function getResetTtl(): int
    {
        return (int) config('auth.password_reset_ttl', 3600);
    }
}
