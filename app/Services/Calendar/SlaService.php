<?php

namespace App\Services\Calendar;

use App\Models\FulfillmentWindow;
use App\Models\Location;
use Carbon\Carbon;

class SlaService
{
    public function getEarliestFulfillmentTime(
        int $locationId,
        string $fulfillmentType,
        ?Carbon $now = null
    ): Carbon {
        $now = $now ?? Carbon::now();

        $location = Location::findOrFail($locationId);
        $timezone = $location->timezone ?? Location::DEFAULT_TIMEZONE;

        $nowInTimezone = $now->copy()->setTimezone($timezone);

        $window = FulfillmentWindow::where('location_id', $locationId)
            ->forFulfillmentType($fulfillmentType)
            ->first();

        $minLeadTime = $window?->min_lead_time_min ?? FulfillmentWindow::DEFAULT_MIN_LEAD_TIME_MIN;

        return $nowInTimezone->copy()->addMinutes($minLeadTime);
    }

    public function meetsLeadTime(
        Carbon $requestedTime,
        int $minLeadTimeMin,
        ?Carbon $now = null
    ): bool {
        $now = $now ?? Carbon::now();

        $earliestTime = $now->copy()->addMinutes($minLeadTimeMin);

        return $requestedTime->gte($earliestTime);
    }

    public function getLeadTimeReason(
        Carbon $requestedTime,
        int $minLeadTimeMin,
        ?Carbon $now = null
    ): ?string {
        if ($this->meetsLeadTime($requestedTime, $minLeadTimeMin, $now)) {
            return null;
        }

        return "Requires at least {$minLeadTimeMin} minutes lead time";
    }

    public function getMinLeadTimeForLocation(
        int $locationId,
        string $fulfillmentType
    ): int {
        $window = FulfillmentWindow::where('location_id', $locationId)
            ->forFulfillmentType($fulfillmentType)
            ->first();

        return $window?->min_lead_time_min ?? FulfillmentWindow::DEFAULT_MIN_LEAD_TIME_MIN;
    }
}
