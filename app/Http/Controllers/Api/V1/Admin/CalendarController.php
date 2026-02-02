<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Calendar\StoreExceptionRequest;
use App\Http\Requests\Calendar\UpdateExceptionRequest;
use App\Http\Requests\Calendar\UpdateFulfillmentWindowRequest;
use App\Http\Requests\Calendar\UpdateWeeklyHoursRequest;
use App\Http\Resources\FulfillmentWindowResource;
use App\Http\Resources\LocationExceptionResource;
use App\Http\Resources\WeeklyHoursResource;
use App\Models\FulfillmentWindow;
use App\Models\Location;
use App\Models\LocationException;
use App\Models\LocationHour;
use App\Services\Calendar\LocationCalendarService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CalendarController extends Controller
{
    public function __construct(
        private LocationCalendarService $calendarService
    ) {}

    public function getHours(Location $location): JsonResponse
    {
        $schedule = $this->calendarService->getWeeklySchedule($location->id);

        return response()->json([
            'data' => new WeeklyHoursResource($schedule),
        ]);
    }

    public function updateHours(UpdateWeeklyHoursRequest $request, Location $location): JsonResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($location, $validated) {
            LocationHour::where('location_id', $location->id)->delete();

            foreach ($validated['hours'] as $hourData) {
                LocationHour::create([
                    'location_id' => $location->id,
                    'day_of_week' => $hourData['day_of_week'],
                    'open_time' => $hourData['open_time'],
                    'close_time' => $hourData['close_time'],
                    'fulfillment_type' => $hourData['fulfillment_type'],
                    'is_closed' => $hourData['is_closed'] ?? false,
                ]);
            }
        });

        $schedule = $this->calendarService->getWeeklySchedule($location->id);

        return response()->json([
            'data' => new WeeklyHoursResource($schedule),
        ]);
    }

    public function listExceptions(Request $request, Location $location): JsonResponse
    {
        $query = LocationException::where('location_id', $location->id)
            ->orderBy('date');

        if ($request->has('from')) {
            $from = Carbon::parse($request->input('from'));
            $query->where('date', '>=', $from);
        }

        if ($request->has('to')) {
            $to = Carbon::parse($request->input('to'));
            $query->where('date', '<=', $to);
        }

        $exceptions = $query->get();

        return response()->json([
            'data' => LocationExceptionResource::collection($exceptions),
            'meta' => [
                'total' => $exceptions->count(),
            ],
        ]);
    }

    public function storeException(StoreExceptionRequest $request, Location $location): JsonResponse
    {
        $validated = $request->validated();

        $exception = LocationException::create([
            'location_id' => $location->id,
            'date' => $validated['date'],
            'type' => $validated['type'],
            'open_time' => $validated['open_time'] ?? null,
            'close_time' => $validated['close_time'] ?? null,
            'fulfillment_type' => $validated['fulfillment_type'] ?? null,
            'reason' => $validated['reason'] ?? null,
        ]);

        return response()->json([
            'data' => new LocationExceptionResource($exception),
        ], 201);
    }

    public function updateException(UpdateExceptionRequest $request, LocationException $exception): JsonResponse
    {
        $validated = $request->validated();

        $exception->update(array_filter($validated, fn ($value) => $value !== null));

        return response()->json([
            'data' => new LocationExceptionResource($exception->fresh()),
        ]);
    }

    public function destroyException(LocationException $exception): JsonResponse
    {
        $exception->delete();

        return response()->json(null, 204);
    }

    public function updateFulfillmentWindow(UpdateFulfillmentWindowRequest $request, Location $location): JsonResponse
    {
        $validated = $request->validated();

        $window = FulfillmentWindow::updateOrCreate(
            [
                'location_id' => $location->id,
                'fulfillment_type' => $validated['fulfillment_type'],
            ],
            [
                'slot_interval_min' => $validated['slot_interval_min'] ?? FulfillmentWindow::DEFAULT_SLOT_INTERVAL_MIN,
                'slot_duration_min' => $validated['slot_duration_min'] ?? FulfillmentWindow::DEFAULT_SLOT_DURATION_MIN,
                'min_lead_time_min' => $validated['min_lead_time_min'] ?? FulfillmentWindow::DEFAULT_MIN_LEAD_TIME_MIN,
                'cutoff_min_before_close' => $validated['cutoff_min_before_close'] ?? FulfillmentWindow::DEFAULT_CUTOFF_MIN_BEFORE_CLOSE,
                'max_days_ahead' => $validated['max_days_ahead'] ?? FulfillmentWindow::DEFAULT_MAX_DAYS_AHEAD,
            ]
        );

        return response()->json([
            'data' => new FulfillmentWindowResource($window),
        ]);
    }

    public function getFulfillmentWindows(Location $location): JsonResponse
    {
        $windows = FulfillmentWindow::where('location_id', $location->id)->get();

        return response()->json([
            'data' => FulfillmentWindowResource::collection($windows),
            'meta' => [
                'total' => $windows->count(),
            ],
        ]);
    }
}
