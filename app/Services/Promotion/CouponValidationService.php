<?php

namespace App\Services\Promotion;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;

class CouponValidationService
{
    public function validate(string $code, Order $order, ?User $user = null): CouponValidationResult
    {
        $coupon = Coupon::byCode($code)->first();

        if ($coupon === null) {
            return CouponValidationResult::failure('not_found', 'This coupon code is not valid');
        }

        if (! $coupon->is_active) {
            return CouponValidationResult::failure('inactive', 'This coupon code is not valid');
        }

        if (! $coupon->isWithinDateWindow()) {
            return CouponValidationResult::failure('date_window', 'This coupon code is not valid');
        }

        if ($coupon->isFixedDiscount() && $coupon->currency !== null && $coupon->currency !== $order->currency) {
            return CouponValidationResult::failure('currency_mismatch', 'This coupon code is not valid');
        }

        if ($coupon->min_subtotal > 0 && $order->subtotal < $coupon->min_subtotal) {
            $minSubtotalFormatted = number_format($coupon->min_subtotal / 100, 2);

            return CouponValidationResult::failure(
                'min_subtotal',
                "Minimum order subtotal of {$minSubtotalFormatted} {$order->currency} required"
            );
        }

        if ($coupon->hasReachedGlobalLimit()) {
            return CouponValidationResult::failure('global_limit', 'This coupon code is not valid');
        }

        $userId = $user?->id ?? $order->user_id;
        if ($coupon->hasReachedUserLimit($userId)) {
            return CouponValidationResult::failure('user_limit', 'You have already used this coupon');
        }

        return CouponValidationResult::success($coupon);
    }

    public function validateForCheckout(Order $order): CouponValidationResult
    {
        if (! $order->hasCoupon()) {
            return CouponValidationResult::success(null);
        }

        $coupon = $order->coupon;

        if ($coupon === null) {
            return CouponValidationResult::failure('not_found', 'Applied coupon no longer exists');
        }

        if (! $coupon->is_active) {
            return CouponValidationResult::failure('inactive', 'Applied coupon is no longer active');
        }

        if (! $coupon->isWithinDateWindow()) {
            return CouponValidationResult::failure('date_window', 'Applied coupon has expired');
        }

        $redemption = $order->couponRedemption;
        if ($redemption !== null && $redemption->isReserved() && $redemption->hasExpired()) {
            return CouponValidationResult::failure('reservation_expired', 'Coupon reservation has expired');
        }

        return CouponValidationResult::success($coupon);
    }
}

class CouponValidationResult
{
    private function __construct(
        public readonly bool $valid,
        public readonly ?Coupon $coupon = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
    ) {}

    public static function success(?Coupon $coupon): self
    {
        return new self(true, $coupon);
    }

    public static function failure(string $errorCode, string $errorMessage): self
    {
        return new self(false, null, $errorCode, $errorMessage);
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function getCoupon(): ?Coupon
    {
        return $this->coupon;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
