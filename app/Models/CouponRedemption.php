<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponRedemption extends Model
{
    public const STATUS_RESERVED = 'reserved';

    public const STATUS_REDEEMED = 'redeemed';

    public const STATUS_RELEASED = 'released';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'coupon_id',
        'user_id',
        'order_id',
        'status',
        'reserved_at',
        'redeemed_at',
        'expires_at',
        'ip_hash',
        'user_agent_hash',
    ];

    protected $casts = [
        'reserved_at' => 'datetime',
        'redeemed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isReserved(): bool
    {
        return $this->status === self::STATUS_RESERVED;
    }

    public function isRedeemed(): bool
    {
        return $this->status === self::STATUS_REDEEMED;
    }

    public function isReleased(): bool
    {
        return $this->status === self::STATUS_RELEASED;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    public function hasExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return now()->gt($this->expires_at);
    }

    public function markAsRedeemed(): bool
    {
        if ($this->status !== self::STATUS_RESERVED) {
            return false;
        }

        $this->status = self::STATUS_REDEEMED;
        $this->redeemed_at = now();
        $this->save();

        return true;
    }

    public function markAsReleased(): bool
    {
        if ($this->status !== self::STATUS_RESERVED) {
            return false;
        }

        $this->status = self::STATUS_RELEASED;
        $this->save();

        return true;
    }

    public function markAsExpired(): bool
    {
        if ($this->status !== self::STATUS_RESERVED) {
            return false;
        }

        $this->status = self::STATUS_EXPIRED;
        $this->save();

        return true;
    }

    public function scopeForOrder($query, int $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    public function scopeReserved($query)
    {
        return $query->where('status', self::STATUS_RESERVED);
    }

    public function scopeRedeemed($query)
    {
        return $query->where('status', self::STATUS_REDEEMED);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_RESERVED, self::STATUS_REDEEMED]);
    }
}
