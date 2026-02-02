<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_PAID = 'paid';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_PREPARING = 'preparing';

    public const STATUS_READY = 'ready';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'location_id',
        'status',
        'subtotal',
        'total',
        'currency',
        'coupon_id',
        'coupon_code',
        'discount_total',
    ];

    protected $casts = [
        'subtotal' => 'integer',
        'total' => 'integer',
        'discount_total' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function couponRedemption(): HasOne
    {
        return $this->hasOne(CouponRedemption::class);
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function isPayable(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_PENDING_PAYMENT,
        ], true);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function hasCoupon(): bool
    {
        return $this->coupon_id !== null;
    }

    public function markAsPaid(): void
    {
        $this->status = self::STATUS_PAID;
        $this->save();
    }

    public function clearCoupon(): void
    {
        $this->coupon_id = null;
        $this->coupon_code = null;
        $this->discount_total = 0;
        $this->total = $this->subtotal;
        $this->save();
    }

    public function applyCouponDiscount(Coupon $coupon, int $discountAmount): void
    {
        $this->coupon_id = $coupon->id;
        $this->coupon_code = $coupon->code;
        $this->discount_total = $discountAmount;
        $this->total = max(0, $this->subtotal - $discountAmount);
        $this->save();
    }

    public function recalculateTotal(): void
    {
        $this->total = max(0, $this->subtotal - $this->discount_total);
        $this->save();
    }
}
