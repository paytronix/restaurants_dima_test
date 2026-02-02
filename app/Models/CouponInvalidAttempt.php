<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponInvalidAttempt extends Model
{
    protected $fillable = [
        'ip_hash',
        'user_id',
        'attempt_count',
        'first_attempt_at',
        'last_attempt_at',
    ];

    protected $casts = [
        'attempt_count' => 'integer',
        'first_attempt_at' => 'datetime',
        'last_attempt_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function incrementAttempt(): void
    {
        $this->attempt_count++;
        $this->last_attempt_at = now();
        $this->save();
    }

    public function resetAttempts(): void
    {
        $this->attempt_count = 0;
        $this->first_attempt_at = now();
        $this->last_attempt_at = now();
        $this->save();
    }

    public function scopeByIpHash($query, string $ipHash)
    {
        return $query->where('ip_hash', $ipHash);
    }

    public function scopeByUserId($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeWithinWindow($query, int $windowSeconds)
    {
        return $query->where('last_attempt_at', '>=', now()->subSeconds($windowSeconds));
    }
}
