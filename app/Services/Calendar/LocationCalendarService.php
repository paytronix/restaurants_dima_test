<?php

namespace App\Services\Calendar;

use App\Models\Location;
use App\Models\LocationException;
use App\Models\LocationHour;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class LocationCalendarService
{
    public function getEffectiveSchedule(
        int $locationId,
        Carbon $from,
        Carbon $to,
        ?string $fulfillmentType = null
    ): array {
        $location = Location::findOrFail($locationId);
        $timezone = $location->timezone ?? Location::DEFAULT_TIMEZONE;

        $fromLocal = $from->copy()->setTimezone($timezone)->startOfDay();
        $toLocal = $to->copy()->setTimezone($timezone)->endOfDay();

        $period = CarbonPeriod::create($fromLocal, $toLocal);

        $days = [];
        foreach ($period as $date) {
            $days[] = $this->getEffectiveHoursForDate($locationId, $date, $fulfillmentType);
        }

        return [
            'location_id' => $locationId,
            'timezone' => $timezone,
            'from' => $fromLocal->toDateString(),
            'to' => $toLocal->toDateString(),
            'days' => $days,
        ];
    }

    public function getEffectiveHoursForDate(
        int $locationId,
        Carbon $date,
        ?string $fulfillmentType = null
    ): array {
        $location = Location::findOrFail($locationId);
        $timezone = $location->timezone ?? Location::DEFAULT_TIMEZONE;

        $dateLocal = $date->copy()->setTimezone($timezone)->startOfDay();
        $dayOfWeek = (int) $dateLocal->dayOfWeek;

        $exceptions = LocationException::where('location_id', $locationId)
            ->forDate($dateLocal)
            ->forFulfillmentType($fulfillmentType)
            ->orderBy('type')
            ->get();

        $closedAllDay = $exceptions->first(fn ($e) => $e->isClosedAllDay());
        if ($closedAllDay !== null) {
            return [
                'date' => $dateLocal->toDateString(),
                'day_of_week' => $dayOfWeek,
                'day_name' => LocationHour::DAY_NAMES[$dayOfWeek] ?? 'Unknown',
                'is_open' => false,
                'reason' => $closedAllDay->reason ?? 'Closed',
                'hours' => [],
                'exceptions' => $this->formatExceptions($exceptions),
            ];
        }

        $customHours = $exceptions->first(fn ($e) => $e->isOpenCustom());
        if ($customHours !== null) {
            $hours = [];
            if ($customHours->open_time !== null && $customHours->close_time !== null) {
                $hours[] = [
                    'open_time' => $this->formatTime($customHours->open_time),
                    'close_time' => $this->formatTime($customHours->close_time),
                    'fulfillment_type' => $customHours->fulfillment_type ?? 'both',
                ];
            }

            return [
                'date' => $dateLocal->toDateString(),
                'day_of_week' => $dayOfWeek,
                'day_name' => LocationHour::DAY_NAMES[$dayOfWeek] ?? 'Unknown',
                'is_open' => count($hours) > 0,
                'reason' => $customHours->reason,
                'hours' => $hours,
                'exceptions' => $this->formatExceptions($exceptions),
            ];
        }

        $weeklyHoursQuery = LocationHour::where('location_id', $locationId)
            ->forDay($dayOfWeek)
            ->open();

        if ($fulfillmentType !== null) {
            $weeklyHoursQuery->forFulfillmentType($fulfillmentType);
        }

        $weeklyHours = $weeklyHoursQuery->orderBy('open_time')->get();

        $hours = $weeklyHours->map(fn ($h) => [
            'open_time' => $this->formatTime($h->open_time),
            'close_time' => $this->formatTime($h->close_time),
            'fulfillment_type' => $h->fulfillment_type,
        ])->toArray();

        $isOpen = count($hours) > 0;
        $reason = $isOpen ? null : LocationHour::DAY_NAMES[$dayOfWeek].' - Closed';

        return [
            'date' => $dateLocal->toDateString(),
            'day_of_week' => $dayOfWeek,
            'day_name' => LocationHour::DAY_NAMES[$dayOfWeek] ?? 'Unknown',
            'is_open' => $isOpen,
            'reason' => $reason,
            'hours' => $hours,
            'exceptions' => $this->formatExceptions($exceptions),
        ];
    }

    public function getBlackoutWindows(
        int $locationId,
        Carbon $date,
        ?string $fulfillmentType = null
    ): array {
        $location = Location::findOrFail($locationId);
        $timezone = $location->timezone ?? Location::DEFAULT_TIMEZONE;

        $dateLocal = $date->copy()->setTimezone($timezone)->startOfDay();

        $blackouts = LocationException::where('location_id', $locationId)
            ->forDate($dateLocal)
            ->blackoutWindows()
            ->forFulfillmentType($fulfillmentType)
            ->get();

        return $blackouts->map(fn ($b) => [
            'open_time' => $this->formatTime($b->open_time),
            'close_time' => $this->formatTime($b->close_time),
            'reason' => $b->reason,
        ])->toArray();
    }

    public function getWeeklySchedule(int $locationId): array
    {
        $location = Location::findOrFail($locationId);
        $timezone = $location->timezone ?? Location::DEFAULT_TIMEZONE;

        $weeklyHours = LocationHour::where('location_id', $locationId)
            ->orderBy('day_of_week')
            ->orderBy('open_time')
            ->get();

        $schedule = [];
        for ($day = 0; $day <= 6; $day++) {
            $dayHours = $weeklyHours->filter(fn ($h) => $h->day_of_week === $day);

            $isClosed = $dayHours->isEmpty() || $dayHours->every(fn ($h) => $h->is_closed);

            $hours = $dayHours
                ->filter(fn ($h) => ! $h->is_closed)
                ->map(fn ($h) => [
                    'id' => $h->id,
                    'open_time' => $this->formatTime($h->open_time),
                    'close_time' => $this->formatTime($h->close_time),
                    'fulfillment_type' => $h->fulfillment_type,
                ])
                ->values()
                ->toArray();

            $schedule[] = [
                'day_of_week' => $day,
                'day_name' => LocationHour::DAY_NAMES[$day] ?? 'Unknown',
                'is_closed' => $isClosed,
                'hours' => $hours,
            ];
        }

        return [
            'location_id' => $locationId,
            'timezone' => $timezone,
            'weekly_hours' => $schedule,
        ];
    }

    private function formatTime($time): string
    {
        if ($time instanceof \DateTimeInterface) {
            return $time->format('H:i');
        }

        if (is_string($time)) {
            return substr($time, 0, 5);
        }

        return (string) $time;
    }

    private function formatExceptions($exceptions): array
    {
        return $exceptions->map(fn ($e) => [
            'id' => $e->id,
            'type' => $e->type,
            'open_time' => $e->open_time ? $this->formatTime($e->open_time) : null,
            'close_time' => $e->close_time ? $this->formatTime($e->close_time) : null,
            'fulfillment_type' => $e->fulfillment_type,
            'reason' => $e->reason,
        ])->toArray();
    }
}
