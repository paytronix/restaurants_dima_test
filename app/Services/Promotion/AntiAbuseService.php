<?php

namespace App\Services\Promotion;

use App\Models\CouponInvalidAttempt;

class AntiAbuseService
{
    private int $attemptLimit;

    private int $windowSeconds;

    public function __construct()
    {
        $this->attemptLimit = (int) config('promotions.invalid_attempt_limit', 5);
        $this->windowSeconds = (int) config('promotions.invalid_attempt_window', 60);
    }

    public function isRateLimited(?string $ipHash, ?int $userId): bool
    {
        if ($ipHash === null && $userId === null) {
            return false;
        }

        $query = CouponInvalidAttempt::query();

        if ($ipHash !== null && $userId !== null) {
            $query->where(function ($q) use ($ipHash, $userId) {
                $q->where('ip_hash', $ipHash)
                    ->orWhere('user_id', $userId);
            });
        } elseif ($ipHash !== null) {
            $query->where('ip_hash', $ipHash);
        } else {
            $query->where('user_id', $userId);
        }

        $query->withinWindow($this->windowSeconds);

        $record = $query->first();

        if ($record === null) {
            return false;
        }

        return $record->attempt_count >= $this->attemptLimit;
    }

    public function recordInvalidAttempt(?string $ipHash, ?int $userId): void
    {
        if ($ipHash === null && $userId === null) {
            return;
        }

        $record = $this->findOrCreateRecord($ipHash, $userId);

        $windowStart = now()->subSeconds($this->windowSeconds);

        if ($record->last_attempt_at !== null && $record->last_attempt_at->lt($windowStart)) {
            $record->resetAttempts();
        } else {
            $record->incrementAttempt();
        }
    }

    public function clearAttempts(?string $ipHash, ?int $userId): void
    {
        if ($ipHash === null && $userId === null) {
            return;
        }

        $query = CouponInvalidAttempt::query();

        if ($ipHash !== null) {
            $query->where('ip_hash', $ipHash);
        }

        if ($userId !== null) {
            $query->orWhere('user_id', $userId);
        }

        $query->delete();
    }

    public function hashIp(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        return hash('sha256', $ip);
    }

    public function hashUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null || $userAgent === '') {
            return null;
        }

        return hash('sha256', $userAgent);
    }

    private function findOrCreateRecord(?string $ipHash, ?int $userId): CouponInvalidAttempt
    {
        $query = CouponInvalidAttempt::query();

        if ($ipHash !== null) {
            $query->where('ip_hash', $ipHash);
        } else {
            $query->where('user_id', $userId);
        }

        $record = $query->first();

        if ($record !== null) {
            return $record;
        }

        return CouponInvalidAttempt::create([
            'ip_hash' => $ipHash,
            'user_id' => $userId,
            'attempt_count' => 0,
            'first_attempt_at' => now(),
            'last_attempt_at' => now(),
        ]);
    }
}
