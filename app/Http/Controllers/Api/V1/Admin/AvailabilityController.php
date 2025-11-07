<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAvailabilityRequest;
use App\Models\ItemAvailability;
use App\Models\MenuItem;
use App\Services\AvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AvailabilityController extends Controller
{
    public function __construct(
        private AvailabilityService $availabilityService
    ) {}

    public function store(StoreAvailabilityRequest $request, MenuItem $item): JsonResponse
    {
        $availability = $this->availabilityService->addAvailabilityWindow($item->id, $request->validated());

        return response()->json([
            'data' => [
                'id' => $availability->id,
                'menu_item_id' => $availability->menu_item_id,
                'day_of_week' => $availability->day_of_week,
                'time_from' => $availability->time_from,
                'time_to' => $availability->time_to,
            ],
        ], 201);
    }

    public function destroy(MenuItem $item, ItemAvailability $availability): Response
    {
        $this->availabilityService->removeAvailabilityWindow($availability->id);

        return response()->noContent();
    }
}
