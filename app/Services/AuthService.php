<?php

namespace App\Services;

use App\Models\CustomerProfile;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AuthService
{
    public function register(array $data): array
    {
        $existingUser = User::where('email', $data['email'])->first();
        if ($existingUser !== null) {
            throw new ConflictHttpException('Email already registered.');
        }

        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')),
                'email' => $data['email'],
                'password' => $data['password'],
                'status' => User::STATUS_ACTIVE,
            ]);

            CustomerProfile::create([
                'user_id' => $user->id,
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
            ]);

            $tokens = $this->issueTokens($user);

            Log::info('User registered', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return [
                'user' => $user,
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'expires_in' => $this->getAccessTokenTtl(),
            ];
        });
    }

    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            Log::warning('Login failed', [
                'email' => $email,
                'reason' => 'invalid_credentials',
            ]);
            throw new UnauthorizedHttpException('Bearer', 'The provided credentials are incorrect.');
        }

        if (! $user->isActive()) {
            Log::warning('Login failed', [
                'user_id' => $user->id,
                'reason' => 'account_not_active',
                'status' => $user->status,
            ]);
            throw new UnauthorizedHttpException('Bearer', 'The provided credentials are incorrect.');
        }

        $tokens = $this->issueTokens($user);

        Log::info('User logged in', [
            'user_id' => $user->id,
        ]);

        return [
            'user' => $user,
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in' => $this->getAccessTokenTtl(),
        ];
    }

    public function refresh(string $refreshToken): array
    {
        $tokenHash = hash('sha256', $refreshToken);

        $storedToken = RefreshToken::where('token_hash', $tokenHash)->first();

        if ($storedToken === null) {
            Log::warning('Refresh token not found');
            throw new UnauthorizedHttpException('Bearer', 'Invalid refresh token.');
        }

        if ($storedToken->isRevoked()) {
            Log::warning('Refresh token reuse detected', [
                'token_id' => $storedToken->id,
                'user_id' => $storedToken->user_id,
            ]);

            $this->revokeTokenFamily($storedToken);

            throw new UnauthorizedHttpException('Bearer', 'Invalid refresh token.');
        }

        if ($storedToken->isExpired()) {
            Log::warning('Refresh token expired', [
                'token_id' => $storedToken->id,
                'user_id' => $storedToken->user_id,
            ]);
            throw new UnauthorizedHttpException('Bearer', 'Refresh token has expired.');
        }

        $user = $storedToken->user;

        if ($user === null || ! $user->isActive()) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid refresh token.');
        }

        return DB::transaction(function () use ($storedToken, $user) {
            $user->tokens()->delete();

            $tokens = $this->issueTokens($user);

            $newRefreshToken = RefreshToken::where('user_id', $user->id)
                ->where('revoked', false)
                ->orderByDesc('id')
                ->first();

            $storedToken->update([
                'revoked' => true,
                'replaced_by' => $newRefreshToken?->id,
            ]);

            Log::info('Token refreshed', [
                'user_id' => $user->id,
                'old_token_id' => $storedToken->id,
                'new_token_id' => $newRefreshToken?->id,
            ]);

            return [
                'user' => $user,
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'expires_in' => $this->getAccessTokenTtl(),
            ];
        });
    }

    public function logout(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->tokens()->delete();

            $user->refreshTokens()
                ->where('revoked', false)
                ->update(['revoked' => true]);

            Log::info('User logged out', [
                'user_id' => $user->id,
            ]);
        });
    }

    public function revokeTokenFamily(RefreshToken $token): void
    {
        $userId = $token->user_id;

        RefreshToken::where('user_id', $userId)
            ->where('revoked', false)
            ->update(['revoked' => true]);

        $user = User::find($userId);
        if ($user !== null) {
            $user->tokens()->delete();
        }

        Log::warning('Token family revoked due to reuse detection', [
            'user_id' => $userId,
            'trigger_token_id' => $token->id,
        ]);
    }

    private function issueTokens(User $user): array
    {
        $accessToken = $user->createToken('access_token', ['*'], now()->addSeconds($this->getAccessTokenTtl()));

        $refreshTokenPlain = 'rt_'.Str::random(64);
        $refreshTokenHash = hash('sha256', $refreshTokenPlain);

        RefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => $refreshTokenHash,
            'revoked' => false,
            'expires_at' => now()->addSeconds($this->getRefreshTokenTtl()),
            'created_at' => now(),
        ]);

        return [
            'access_token' => $accessToken->plainTextToken,
            'refresh_token' => $refreshTokenPlain,
        ];
    }

    private function getAccessTokenTtl(): int
    {
        return (int) config('auth.jwt_access_ttl', 900);
    }

    private function getRefreshTokenTtl(): int
    {
        return (int) config('auth.jwt_refresh_ttl', 1209600);
    }
}
