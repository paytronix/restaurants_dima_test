<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupon extends Model
{
    use SoftDeletes;

    public const DISCOUNT_TYPE_PERCENT = 'percent';

    public const DISCOUNT_TYPE_FIXED = 'fixed';

    protected $fillable = [
        'code',
        'name',
        'discount_type',
        'discount_value',
        'currency',
        'starts_at',
        'ends_at',
        'min_subtotal',
        'max_uses_total',
        'max_uses_per_customer',
        'is_active',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'min_subtotal' => 'integer',
        'max_uses_total' => 'integer',
        'max_uses_per_customer' => 'integer',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(CouponTarget::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = strtoupper(trim($value));
    }

    public function isPercentDiscount(): bool
    {
        return $this->discount_type === self::DISCOUNT_TYPE_PERCENT;
    }

    public function isFixedDiscount(): bool
    {
        return $this->discount_type === self::DISCOUNT_TYPE_FIXED;
    }

    public function isWithinDateWindow(): bool
    {
        $now = now();

        if ($this->starts_at !== null && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at !== null && $now->gt($this->ends_at)) {
            return false;
        }

        return true;
    }

    public function getRedeemedCount(): int
    {
        return $this->redemptions()
            ->where('status', CouponRedemption::STATUS_REDEEMED)
            ->count();
    }

    public function getRedeemedCountForUser(?int $userId): int
    {
        if ($userId === null) {
            return 0;
        }

        return $this->redemptions()
            ->where('user_id', $userId)
            ->where('status', CouponRedemption::STATUS_REDEEMED)
            ->count();
    }

    public function hasReachedGlobalLimit(): bool
    {
        if ($this->max_uses_total === null) {
            return false;
        }

        return $this->getRedeemedCount() >= $this->max_uses_total;
    }

    public function hasReachedUserLimit(?int $userId): bool
    {
        if ($this->max_uses_per_customer === null) {
            return false;
        }

        if ($userId === null) {
            return false;
        }

        return $this->getRedeemedCountForUser($userId) >= $this->max_uses_per_customer;
    }

    public function hasTargets(): bool
    {
        return $this->targets()->exists();
    }

    public function getCategoryTargetIds(): array
    {
        return $this->targets()
            ->where('target_type', CouponTarget::TYPE_CATEGORY)
            ->pluck('target_id')
            ->toArray();
    }

    public function getMenuItemTargetIds(): array
    {
        return $this->targets()
            ->where('target_type', CouponTarget::TYPE_MENU_ITEM)
            ->pluck('target_id')
            ->toArray();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeValidNow($query)
    {
        $now = now();

        return $query
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }

    public function scopeByCode($query, string $code)
    {
        return $query->where('code', strtoupper(trim($code)));
    }
}
