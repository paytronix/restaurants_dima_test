<?php

namespace App\Services\Promotion;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PromotionService
{
    private int $reservationTtl;

    public function __construct(
        private CouponValidationService $validationService,
        private DiscountCalculator $discountCalculator,
        private AntiAbuseService $antiAbuseService,
    ) {
        $this->reservationTtl = (int) config('promotions.reservation_ttl', 900);
    }

    public function applyCoupon(
        Order $order,
        string $code,
        ?User $user = null,
        ?string $ipHash = null,
        ?string $userAgentHash = null
    ): PromotionResult {
        $userId = $user?->id ?? $order->user_id;

        if ($this->antiAbuseService->isRateLimited($ipHash, $userId)) {
            return PromotionResult::rateLimited();
        }

        $validationResult = $this->validationService->validate($code, $order, $user);

        if (! $validationResult->isValid()) {
            $this->antiAbuseService->recordInvalidAttempt($ipHash, $userId);

            return PromotionResult::validationFailed(
                $validationResult->getErrorCode() ?? 'validation_error',
                $validationResult->getErrorMessage() ?? 'Validation failed'
            );
        }

        $coupon = $validationResult->getCoupon();

        if ($coupon === null) {
            return PromotionResult::validationFailed('not_found', 'Coupon not found');
        }

        $discountResult = $this->discountCalculator->calculate($coupon, $order);

        try {
            DB::beginTransaction();

            if ($order->hasCoupon()) {
                $this->releaseExistingReservation($order);
            }

            $reservation = $this->createReservation($coupon, $order, $userId, $ipHash, $userAgentHash);

            $order->applyCouponDiscount($coupon, $discountResult->getDiscountAmount());

            DB::commit();

            $this->antiAbuseService->clearAttempts($ipHash, $userId);

            return PromotionResult::success($order, $coupon, $discountResult->getDiscountAmount());

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to apply coupon', [
                'order_id' => $order->id,
                'coupon_code' => $code,
                'error' => $e->getMessage(),
            ]);

            return PromotionResult::failure('internal_error', 'Failed to apply coupon');
        }
    }

    public function removeCoupon(Order $order): PromotionResult
    {
        if (! $order->hasCoupon()) {
            return PromotionResult::success($order, null, 0);
        }

        try {
            DB::beginTransaction();

            $this->releaseExistingReservation($order);

            $order->clearCoupon();

            DB::commit();

            return PromotionResult::success($order, null, 0);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to remove coupon', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return PromotionResult::failure('internal_error', 'Failed to remove coupon');
        }
    }

    public function redeemReservation(Order $order): bool
    {
        $redemption = CouponRedemption::forOrder($order->id)
            ->reserved()
            ->first();

        if ($redemption === null) {
            return false;
        }

        if ($redemption->hasExpired()) {
            $redemption->markAsExpired();

            return false;
        }

        return $redemption->markAsRedeemed();
    }

    public function releaseReservation(Order $order): bool
    {
        $redemption = CouponRedemption::forOrder($order->id)
            ->reserved()
            ->first();

        if ($redemption === null) {
            return false;
        }

        return $redemption->markAsReleased();
    }

    public function checkAndExpireReservation(Order $order): bool
    {
        $redemption = CouponRedemption::forOrder($order->id)
            ->reserved()
            ->first();

        if ($redemption === null) {
            return false;
        }

        if (! $redemption->hasExpired()) {
            return false;
        }

        $redemption->markAsExpired();
        $order->clearCoupon();

        return true;
    }

    private function createReservation(
        Coupon $coupon,
        Order $order,
        ?int $userId,
        ?string $ipHash,
        ?string $userAgentHash
    ): CouponRedemption {
        return CouponRedemption::create([
            'coupon_id' => $coupon->id,
            'user_id' => $userId,
            'order_id' => $order->id,
            'status' => CouponRedemption::STATUS_RESERVED,
            'reserved_at' => now(),
            'expires_at' => now()->addSeconds($this->reservationTtl),
            'ip_hash' => $ipHash,
            'user_agent_hash' => $userAgentHash,
        ]);
    }

    private function releaseExistingReservation(Order $order): void
    {
        $existingRedemption = CouponRedemption::forOrder($order->id)
            ->reserved()
            ->first();

        if ($existingRedemption !== null) {
            $existingRedemption->markAsReleased();
        }
    }
}

class PromotionResult
{
    private function __construct(
        public readonly bool $success,
        public readonly ?Order $order = null,
        public readonly ?Coupon $coupon = null,
        public readonly int $discountAmount = 0,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly bool $isRateLimited = false,
    ) {}

    public static function success(Order $order, ?Coupon $coupon, int $discountAmount): self
    {
        return new self(true, $order, $coupon, $discountAmount);
    }

    public static function validationFailed(string $errorCode, string $errorMessage): self
    {
        return new self(false, errorCode: $errorCode, errorMessage: $errorMessage);
    }

    public static function failure(string $errorCode, string $errorMessage): self
    {
        return new self(false, errorCode: $errorCode, errorMessage: $errorMessage);
    }

    public static function rateLimited(): self
    {
        return new self(
            false,
            errorCode: 'rate_limited',
            errorMessage: 'Too many invalid coupon attempts. Please try again later.',
            isRateLimited: true
        );
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isRateLimited(): bool
    {
        return $this->isRateLimited;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function getCoupon(): ?Coupon
    {
        return $this->coupon;
    }

    public function getDiscountAmount(): int
    {
        return $this->discountAmount;
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
