<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    public const TERMINAL_STATUSES = [
        self::STATUS_SUCCEEDED,
        self::STATUS_FAILED,
        self::STATUS_CANCELLED,
        self::STATUS_EXPIRED,
    ];

    protected $fillable = [
        'order_id',
        'provider',
        'provider_payment_id',
        'status',
        'amount',
        'currency',
        'idempotency_key_hash',
        'checkout_url',
        'client_secret',
        'metadata_json',
        'error_message',
    ];

    protected $casts = [
        'amount' => 'integer',
        'metadata_json' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function canTransitionTo(string $newStatus): bool
    {
        $allowedTransitions = [
            self::STATUS_PENDING => [self::STATUS_PROCESSING, self::STATUS_EXPIRED, self::STATUS_CANCELLED],
            self::STATUS_PROCESSING => [self::STATUS_SUCCEEDED, self::STATUS_FAILED, self::STATUS_CANCELLED],
        ];

        $allowed = $allowedTransitions[$this->status] ?? [];

        return in_array($newStatus, $allowed, true);
    }

    public function transitionTo(string $newStatus): bool
    {
        if (! $this->canTransitionTo($newStatus)) {
            return false;
        }

        $this->status = $newStatus;
        $this->save();

        return true;
    }

    public function markAsProcessing(): bool
    {
        return $this->transitionTo(self::STATUS_PROCESSING);
    }

    public function markAsSucceeded(): bool
    {
        return $this->transitionTo(self::STATUS_SUCCEEDED);
    }

    public function markAsFailed(?string $errorMessage = null): bool
    {
        if ($errorMessage !== null) {
            $this->error_message = $errorMessage;
        }

        return $this->transitionTo(self::STATUS_FAILED);
    }

    public function markAsCancelled(): bool
    {
        return $this->transitionTo(self::STATUS_CANCELLED);
    }

    public function scopeNonTerminal($query)
    {
        return $query->whereNotIn('status', self::TERMINAL_STATUSES);
    }

    public function scopeForProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }
}
