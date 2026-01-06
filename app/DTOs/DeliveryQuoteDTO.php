<?php

namespace App\DTOs;

class DeliveryQuoteDTO
{
    public function __construct(
        public readonly bool $serviceable,
        public readonly ?int $deliveryZoneId,
        public readonly ?string $deliveryFee,
        public readonly string $currency,
        public readonly ?string $minOrderAmount,
        public readonly ?string $freeDeliveryThreshold,
        public readonly ?int $estimatedDeliveryMinutes,
        public readonly bool $meetsMinimumOrder = true
    ) {}

    public function toArray(): array
    {
        return [
            'serviceable' => $this->serviceable,
            'delivery_zone_id' => $this->deliveryZoneId,
            'delivery_fee' => $this->deliveryFee,
            'currency' => $this->currency,
            'min_order_amount' => $this->minOrderAmount,
            'free_delivery_threshold' => $this->freeDeliveryThreshold,
            'estimated_delivery_minutes' => $this->estimatedDeliveryMinutes,
        ];
    }
}
