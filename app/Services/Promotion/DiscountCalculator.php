<?php

namespace App\Services\Promotion;

use App\Models\Coupon;
use App\Models\Order;

class DiscountCalculator
{
    public function calculate(Coupon $coupon, Order $order): DiscountResult
    {
        $eligibleSubtotal = $this->calculateEligibleSubtotal($coupon, $order);

        if ($eligibleSubtotal <= 0) {
            return DiscountResult::zero();
        }

        $discountAmount = $this->computeDiscount($coupon, $eligibleSubtotal);

        $discountAmount = min($discountAmount, $order->subtotal);

        $discountAmount = max(0, $discountAmount);

        return DiscountResult::success($discountAmount, $eligibleSubtotal);
    }

    private function calculateEligibleSubtotal(Coupon $coupon, Order $order): int
    {
        if (! $coupon->hasTargets()) {
            return $order->subtotal;
        }

        return $order->subtotal;
    }

    private function computeDiscount(Coupon $coupon, int $eligibleSubtotal): int
    {
        if ($coupon->isPercentDiscount()) {
            $discountPercent = (float) $coupon->discount_value;
            $discountAmount = (int) round($eligibleSubtotal * ($discountPercent / 100));

            return $discountAmount;
        }

        if ($coupon->isFixedDiscount()) {
            $fixedAmount = (int) round((float) $coupon->discount_value * 100);

            return min($fixedAmount, $eligibleSubtotal);
        }

        return 0;
    }
}

class DiscountResult
{
    private function __construct(
        public readonly int $discountAmount,
        public readonly int $eligibleSubtotal,
        public readonly bool $hasDiscount,
    ) {}

    public static function success(int $discountAmount, int $eligibleSubtotal): self
    {
        return new self($discountAmount, $eligibleSubtotal, $discountAmount > 0);
    }

    public static function zero(): self
    {
        return new self(0, 0, false);
    }

    public function getDiscountAmount(): int
    {
        return $this->discountAmount;
    }

    public function getEligibleSubtotal(): int
    {
        return $this->eligibleSubtotal;
    }

    public function hasDiscount(): bool
    {
        return $this->hasDiscount;
    }
}
