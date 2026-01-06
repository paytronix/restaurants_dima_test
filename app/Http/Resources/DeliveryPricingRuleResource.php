<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryPricingRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'delivery_zone_id' => $this->delivery_zone_id,
            'fee_amount' => $this->fee_amount,
            'min_order_amount' => $this->min_order_amount,
            'free_delivery_threshold' => $this->free_delivery_threshold,
            'currency' => $this->currency,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
