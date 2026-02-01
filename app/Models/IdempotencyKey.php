<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'key_hash',
        'scope',
        'request_hash',
        'response_json',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'response_json' => 'array',
        'expires_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function markAsCompleted(array $response): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->response_json = $response;
        $this->save();
    }

    public function markAsFailed(): void
    {
        $this->status = self::STATUS_FAILED;
        $this->save();
    }

    public function scopeNotExpired($query)
    {
        return $query->where('expires_at', '>', now());
    }

    public function scopeForScope($query, string $scope)
    {
        return $query->where('scope', $scope);
    }
}
