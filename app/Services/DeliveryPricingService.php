<?php

namespace App\Services;

use App\DTOs\DeliveryQuoteDTO;
use App\Models\DeliveryZone;

class DeliveryPricingService
{
    public function __construct(
        private LeadTimeService $leadTimeService
    ) {}

    public function quote(int $locationId, int $zoneId, string $orderSubtotal): DeliveryQuoteDTO
    {
        $zone = DeliveryZone::with('pricingRule')->find($zoneId);

        if ($zone === null) {
            return $this->notServiceableQuote();
        }

        $rule = $zone->pricingRule;
        $currency = config('app.currency', 'PLN');

        if ($rule === null) {
            $estimatedMinutes = $this->leadTimeService->estimateDelivery($locationId, $zoneId);

            return new DeliveryQuoteDTO(
                serviceable: true,
                deliveryZoneId: $zoneId,
                deliveryFee: '0.00',
                currency: $currency,
                minOrderAmount: '0.00',
                freeDeliveryThreshold: null,
                estimatedDeliveryMinutes: $estimatedMinutes,
                meetsMinimumOrder: true
            );
        }

        $subtotal = (float) $orderSubtotal;
        $fee = $rule->fee_amount;
        $currency = $rule->currency;

        if ($rule->free_delivery_threshold !== null
            && $subtotal >= (float) $rule->free_delivery_threshold) {
            $fee = '0.00';
        }

        $meetsMinimum = $subtotal >= (float) $rule->min_order_amount;
        $estimatedMinutes = $this->leadTimeService->estimateDelivery($locationId, $zoneId);

        return new DeliveryQuoteDTO(
            serviceable: true,
            deliveryZoneId: $zoneId,
            deliveryFee: number_format((float) $fee, 2, '.', ''),
            currency: $currency,
            minOrderAmount: number_format((float) $rule->min_order_amount, 2, '.', ''),
            freeDeliveryThreshold: $rule->free_delivery_threshold !== null
                ? number_format((float) $rule->free_delivery_threshold, 2, '.', '')
                : null,
            estimatedDeliveryMinutes: $estimatedMinutes,
            meetsMinimumOrder: $meetsMinimum
        );
    }

    public function notServiceableQuote(): DeliveryQuoteDTO
    {
        return new DeliveryQuoteDTO(
            serviceable: false,
            deliveryZoneId: null,
            deliveryFee: null,
            currency: config('app.currency', 'PLN'),
            minOrderAmount: null,
            freeDeliveryThreshold: null,
            estimatedDeliveryMinutes: null,
            meetsMinimumOrder: false
        );
    }
}
