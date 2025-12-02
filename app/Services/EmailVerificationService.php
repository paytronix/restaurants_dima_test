<?php

namespace App\Services;

use App\Models\EmailVerification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class EmailVerificationService
{
    public function sendVerificationEmail(User $user): void
    {
        if ($user->isEmailVerified()) {
            throw new BadRequestHttpException('Email is already verified.');
        }

        $user->emailVerifications()
            ->where('consumed_at', null)
            ->update(['consumed_at' => now()]);

        $token = Str::random(64);

        EmailVerification::create([
            'user_id' => $user->id,
            'token' => $token,
            'expires_at' => now()->addSeconds($this->getVerificationTtl()),
            'created_at' => now(),
        ]);

        Log::info('Email verification requested', [
            'user_id' => $user->id,
        ]);
    }

    public function verify(string $token): bool
    {
        $verification = EmailVerification::where('token', $token)->first();

        if ($verification === null) {
            Log::warning('Email verification failed: token not found');

            return false;
        }

        if (! $verification->isValid()) {
            Log::warning('Email verification failed: token invalid', [
                'token_id' => $verification->id,
                'expired' => $verification->isExpired(),
                'consumed' => $verification->isConsumed(),
            ]);

            return false;
        }

        $verification->update(['consumed_at' => now()]);

        $user = $verification->user;
        if ($user !== null) {
            $user->update(['email_verified_at' => now()]);

            Log::info('Email verified', [
                'user_id' => $user->id,
            ]);
        }

        return true;
    }

    public function isVerified(User $user): bool
    {
        return $user->isEmailVerified();
    }

    private function getVerificationTtl(): int
    {
        return (int) config('auth.email_verification_ttl', 86400);
    }
}
