<?php

namespace App\Services\Calendar;

use App\Models\FulfillmentWindow;
use App\Models\Location;
use Carbon\Carbon;

class SlotGeneratorService
{
    public function __construct(
        private LocationCalendarService $calendarService,
        private CutoffService $cutoffService,
        private SlaService $slaService
    ) {}

    public function generateSlots(
        int $locationId,
        string $date,
        string $fulfillmentType,
        ?Carbon $now = null
    ): array {
        $now = $now ?? Carbon::now();

        $location = Location::findOrFail($locationId);
        $timezone = $location->timezone ?? Location::DEFAULT_TIMEZONE;

        $dateCarbon = Carbon::parse($date, $timezone)->startOfDay();
        $nowInTimezone = $now->copy()->setTimezone($timezone);

        $window = FulfillmentWindow::where('location_id', $locationId)
            ->forFulfillmentType($fulfillmentType)
            ->first();

        $slotInterval = $window?->slot_interval_min ?? FulfillmentWindow::DEFAULT_SLOT_INTERVAL_MIN;
        $slotDuration = $window?->slot_duration_min ?? FulfillmentWindow::DEFAULT_SLOT_DURATION_MIN;
        $minLeadTime = $window?->min_lead_time_min ?? FulfillmentWindow::DEFAULT_MIN_LEAD_TIME_MIN;
        $cutoffMinutes = $window?->cutoff_min_before_close ?? FulfillmentWindow::DEFAULT_CUTOFF_MIN_BEFORE_CLOSE;
        $maxDaysAhead = $window?->max_days_ahead ?? FulfillmentWindow::DEFAULT_MAX_DAYS_AHEAD;

        $maxDate = $nowInTimezone->copy()->addDays($maxDaysAhead)->endOfDay();
        if ($dateCarbon->gt($maxDate)) {
            return [
                'location_id' => $locationId,
                'date' => $date,
                'timezone' => $timezone,
                'fulfillment_type' => $fulfillmentType,
                'slots' => [],
                'error' => "Date is beyond the maximum allowed ({$maxDaysAhead} days ahead)",
            ];
        }

        $effectiveHours = $this->calendarService->getEffectiveHoursForDate(
            $locationId,
            $dateCarbon,
            $fulfillmentType
        );

        if (! $effectiveHours['is_open']) {
            return [
                'location_id' => $locationId,
                'date' => $date,
                'timezone' => $timezone,
                'fulfillment_type' => $fulfillmentType,
                'slots' => [],
                'reason' => $effectiveHours['reason'] ?? 'Location is closed',
            ];
        }

        $blackouts = $this->calendarService->getBlackoutWindows(
            $locationId,
            $dateCarbon,
            $fulfillmentType
        );

        $slots = [];
        foreach ($effectiveHours['hours'] as $hourBlock) {
            if (! $this->appliesToFulfillmentType($hourBlock['fulfillment_type'], $fulfillmentType)) {
                continue;
            }

            $openTime = Carbon::parse($date.' '.$hourBlock['open_time'], $timezone);
            $closeTime = Carbon::parse($date.' '.$hourBlock['close_time'], $timezone);

            if ($closeTime->lte($openTime)) {
                continue;
            }

            $currentSlot = $openTime->copy();

            while ($currentSlot->copy()->addMinutes($slotDuration)->lte($closeTime)) {
                $slotStart = $currentSlot->copy();
                $slotEnd = $currentSlot->copy()->addMinutes($slotDuration);

                $isBlackedOut = $this->isSlotInBlackout($slotStart, $slotEnd, $blackouts, $date, $timezone);

                $isOrderable = true;
                $reason = null;

                if ($isBlackedOut) {
                    $isOrderable = false;
                    $reason = 'Slot is in a blackout window';
                } elseif (! $this->slaService->meetsLeadTime($slotStart, $minLeadTime, $nowInTimezone)) {
                    $isOrderable = false;
                    $reason = "Requires at least {$minLeadTime} minutes lead time";
                } elseif (! $this->cutoffService->isWithinCutoff($slotStart, $closeTime, $cutoffMinutes)) {
                    $isOrderable = false;
                    $reason = 'Past cutoff time for this day';
                }

                $slots[] = [
                    'slot_start' => $slotStart->toIso8601String(),
                    'slot_end' => $slotEnd->toIso8601String(),
                    'is_orderable' => $isOrderable,
                    'reason' => $reason,
                ];

                $currentSlot->addMinutes($slotInterval);
            }
        }

        return [
            'location_id' => $locationId,
            'date' => $date,
            'timezone' => $timezone,
            'fulfillment_type' => $fulfillmentType,
            'slots' => $slots,
        ];
    }

    public function isSlotOrderable(
        int $locationId,
        Carbon $slotStart,
        string $fulfillmentType,
        ?Carbon $now = null
    ): array {
        $now = $now ?? Carbon::now();

        $location = Location::findOrFail($locationId);
        $timezone = $location->timezone ?? Location::DEFAULT_TIMEZONE;

        $slotStartLocal = $slotStart->copy()->setTimezone($timezone);
        $nowInTimezone = $now->copy()->setTimezone($timezone);
        $date = $slotStartLocal->toDateString();

        $window = FulfillmentWindow::where('location_id', $locationId)
            ->forFulfillmentType($fulfillmentType)
            ->first();

        $minLeadTime = $window?->min_lead_time_min ?? FulfillmentWindow::DEFAULT_MIN_LEAD_TIME_MIN;
        $cutoffMinutes = $window?->cutoff_min_before_close ?? FulfillmentWindow::DEFAULT_CUTOFF_MIN_BEFORE_CLOSE;
        $maxDaysAhead = $window?->max_days_ahead ?? FulfillmentWindow::DEFAULT_MAX_DAYS_AHEAD;

        $maxDate = $nowInTimezone->copy()->addDays($maxDaysAhead)->endOfDay();
        if ($slotStartLocal->gt($maxDate)) {
            return [
                'valid' => false,
                'reason' => "Date is beyond the maximum allowed ({$maxDaysAhead} days ahead)",
            ];
        }

        $effectiveHours = $this->calendarService->getEffectiveHoursForDate(
            $locationId,
            $slotStartLocal,
            $fulfillmentType
        );

        if (! $effectiveHours['is_open']) {
            return [
                'valid' => false,
                'reason' => $effectiveHours['reason'] ?? 'Location is closed on this date',
            ];
        }

        $isWithinHours = false;
        $closeTime = null;

        foreach ($effectiveHours['hours'] as $hourBlock) {
            if (! $this->appliesToFulfillmentType($hourBlock['fulfillment_type'], $fulfillmentType)) {
                continue;
            }

            $openTime = Carbon::parse($date.' '.$hourBlock['open_time'], $timezone);
            $blockCloseTime = Carbon::parse($date.' '.$hourBlock['close_time'], $timezone);

            if ($slotStartLocal->gte($openTime) && $slotStartLocal->lt($blockCloseTime)) {
                $isWithinHours = true;
                $closeTime = $blockCloseTime;
                break;
            }
        }

        if (! $isWithinHours) {
            return [
                'valid' => false,
                'reason' => 'Requested time is outside operating hours',
            ];
        }

        $blackouts = $this->calendarService->getBlackoutWindows(
            $locationId,
            $slotStartLocal,
            $fulfillmentType
        );

        $slotDuration = $window?->slot_duration_min ?? FulfillmentWindow::DEFAULT_SLOT_DURATION_MIN;
        $slotEnd = $slotStartLocal->copy()->addMinutes($slotDuration);

        if ($this->isSlotInBlackout($slotStartLocal, $slotEnd, $blackouts, $date, $timezone)) {
            return [
                'valid' => false,
                'reason' => 'Requested time is in a blackout window',
            ];
        }

        if (! $this->slaService->meetsLeadTime($slotStartLocal, $minLeadTime, $nowInTimezone)) {
            return [
                'valid' => false,
                'reason' => "Requires at least {$minLeadTime} minutes lead time",
            ];
        }

        if ($closeTime !== null && ! $this->cutoffService->isWithinCutoff($slotStartLocal, $closeTime, $cutoffMinutes)) {
            return [
                'valid' => false,
                'reason' => 'Past cutoff time for this day',
            ];
        }

        return [
            'valid' => true,
            'reason' => null,
        ];
    }

    private function appliesToFulfillmentType(string $hourType, string $requestedType): bool
    {
        if ($hourType === 'both') {
            return true;
        }

        return $hourType === $requestedType;
    }

    private function isSlotInBlackout(
        Carbon $slotStart,
        Carbon $slotEnd,
        array $blackouts,
        string $date,
        string $timezone
    ): bool {
        foreach ($blackouts as $blackout) {
            if ($blackout['open_time'] === null || $blackout['close_time'] === null) {
                continue;
            }

            $blackoutStart = Carbon::parse($date.' '.$blackout['open_time'], $timezone);
            $blackoutEnd = Carbon::parse($date.' '.$blackout['close_time'], $timezone);

            if ($slotStart->lt($blackoutEnd) && $slotEnd->gt($blackoutStart)) {
                return true;
            }
        }

        return false;
    }
}
