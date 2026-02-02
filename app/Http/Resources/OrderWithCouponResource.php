<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderWithCouponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $couponData = null;

        if ($this->coupon !== null) {
            $couponData = [
                'code' => $this->coupon->code,
                'discount_type' => $this->coupon->discount_type,
                'discount_value' => $this->coupon->discount_value,
            ];
        }

        return [
            'id' => $this->id,
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'total' => $this->total,
            'currency' => $this->currency,
            'coupon' => $couponData,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
