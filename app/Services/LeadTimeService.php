<?php

namespace App\Services;

use App\Models\LeadTimeSetting;

class LeadTimeService
{
    private const DEFAULT_PICKUP_TIME = 20;

    private const DEFAULT_DELIVERY_TIME = 45;

    public function estimatePickup(int $locationId): int
    {
        $settings = LeadTimeSetting::where('location_id', $locationId)->first();

        if ($settings === null) {
            return self::DEFAULT_PICKUP_TIME;
        }

        return $settings->pickup_lead_time_min;
    }

    public function estimateDelivery(int $locationId, ?int $zoneId = null): int
    {
        $settings = LeadTimeSetting::where('location_id', $locationId)->first();

        if ($settings === null) {
            return self::DEFAULT_DELIVERY_TIME;
        }

        $baseTime = $settings->delivery_lead_time_min;
        $extraTime = $settings->zone_extra_time_min;

        return $baseTime + $extraTime;
    }
}
