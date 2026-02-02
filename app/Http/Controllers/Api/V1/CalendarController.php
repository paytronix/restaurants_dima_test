<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Calendar\CalendarRangeRequest;
use App\Http\Requests\Calendar\SlotsRequest;
use App\Http\Requests\Calendar\ValidateFulfillmentRequest;
use App\Http\Resources\CalendarResource;
use App\Http\Resources\FulfillmentValidationResource;
use App\Http\Resources\SlotsResource;
use App\Http\Resources\WeeklyHoursResource;
use App\Models\Location;
use App\Services\Calendar\LocationCalendarService;
use App\Services\Calendar\SlaService;
use App\Services\Calendar\SlotGeneratorService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class CalendarController extends Controller
{
    public function __construct(
        private LocationCalendarService $calendarService,
        private SlotGeneratorService $slotGeneratorService,
        private SlaService $slaService
    ) {}

    public function hours(Location $location): JsonResponse
    {
        if (! $location->isActive()) {
            return response()->json([
                'title' => 'Not Found',
                'detail' => 'Location not found.',
                'status' => 404,
            ], 404);
        }

        $schedule = $this->calendarService->getWeeklySchedule($location->id);

        return response()->json([
            'data' => new WeeklyHoursResource($schedule),
        ]);
    }

    public function calendar(CalendarRangeRequest $request, Location $location): JsonResponse
    {
        if (! $location->isActive()) {
            return response()->json([
                'title' => 'Not Found',
                'detail' => 'Location not found.',
                'status' => 404,
            ], 404);
        }

        $validated = $request->validated();

        $from = Carbon::parse($validated['from']);
        $to = Carbon::parse($validated['to']);
        $fulfillmentType = $validated['fulfillment_type'] ?? null;

        $schedule = $this->calendarService->getEffectiveSchedule(
            $location->id,
            $from,
            $to,
            $fulfillmentType
        );

        $openDays = collect($schedule['days'])->filter(fn ($day) => $day['is_open'])->count();

        return response()->json([
            'data' => new CalendarResource($schedule),
            'meta' => [
                'total_days' => count($schedule['days']),
                'open_days' => $openDays,
            ],
        ]);
    }

    public function slots(SlotsRequest $request, Location $location): JsonResponse
    {
        if (! $location->isActive()) {
            return response()->json([
                'title' => 'Not Found',
                'detail' => 'Location not found.',
                'status' => 404,
            ], 404);
        }

        $validated = $request->validated();

        $date = $validated['date'];
        $fulfillmentType = $validated['fulfillment_type'];
        $now = isset($validated['now']) ? Carbon::parse($validated['now']) : null;

        $slots = $this->slotGeneratorService->generateSlots(
            $location->id,
            $date,
            $fulfillmentType,
            $now
        );

        $orderableSlots = collect($slots['slots'])->filter(fn ($slot) => $slot['is_orderable'])->count();

        $etag = md5(json_encode($slots));

        return response()->json([
            'data' => new SlotsResource($slots),
            'meta' => [
                'total_slots' => count($slots['slots']),
                'orderable_slots' => $orderableSlots,
            ],
        ])
            ->header('Cache-Control', 'public, max-age=60')
            ->header('ETag', '"'.$etag.'"');
    }

    public function validateFulfillment(ValidateFulfillmentRequest $request, Location $location): JsonResponse
    {
        if (! $location->isActive()) {
            return response()->json([
                'title' => 'Not Found',
                'detail' => 'Location not found.',
                'status' => 404,
            ], 404);
        }

        $validated = $request->validated();

        $fulfillmentType = $validated['fulfillment_type'];
        $requestedAt = Carbon::parse($validated['requested_at']);

        $timezone = $location->timezone ?? Location::DEFAULT_TIMEZONE;
        $normalizedRequestedAt = $requestedAt->copy()->setTimezone($timezone);

        $result = $this->slotGeneratorService->isSlotOrderable(
            $location->id,
            $requestedAt,
            $fulfillmentType
        );

        $earliestPossibleAt = $this->slaService->getEarliestFulfillmentTime(
            $location->id,
            $fulfillmentType
        );

        $responseData = [
            'valid' => $result['valid'],
            'normalized_requested_at' => $normalizedRequestedAt->toIso8601String(),
            'earliest_possible_at' => $earliestPossibleAt->toIso8601String(),
            'reason' => $result['reason'],
        ];

        return response()->json([
            'data' => new FulfillmentValidationResource($responseData),
        ]);
    }
}
