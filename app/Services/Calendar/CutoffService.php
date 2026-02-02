<?php

namespace App\Services\Calendar;

use Carbon\Carbon;

class CutoffService
{
    public function isWithinCutoff(
        Carbon $slotStart,
        Carbon $closeTime,
        int $cutoffMinutes
    ): bool {
        $cutoffTime = $closeTime->copy()->subMinutes($cutoffMinutes);

        return $slotStart->lte($cutoffTime);
    }

    public function getLastOrderableSlot(
        Carbon $closeTime,
        int $cutoffMinutes
    ): Carbon {
        return $closeTime->copy()->subMinutes($cutoffMinutes);
    }

    public function getCutoffReason(
        Carbon $slotStart,
        Carbon $closeTime,
        int $cutoffMinutes
    ): ?string {
        if ($this->isWithinCutoff($slotStart, $closeTime, $cutoffMinutes)) {
            return null;
        }

        return 'Past cutoff time for this day';
    }
}
