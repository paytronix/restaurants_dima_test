<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Location\DeliveryQuoteRequest;
use App\Http\Resources\DeliveryQuoteResource;
use App\Http\Resources\LocationResource;
use App\Http\Resources\PickupPointResource;
use App\Models\Location;
use App\Services\DeliveryPricingService;
use App\Services\GeofenceService;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    public function __construct(
        private GeofenceService $geofenceService,
        private DeliveryPricingService $pricingService
    ) {}

    public function index(): JsonResponse
    {
        $locations = Location::active()
            ->with(['pickupPoints' => fn ($q) => $q->active(), 'deliveryZones' => fn ($q) => $q->active()])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => LocationResource::collection($locations),
            'meta' => [
                'total' => $locations->count(),
            ],
        ]);
    }

    public function show(Location $location): JsonResponse
    {
        if (! $location->isActive()) {
            return response()->json([
                'title' => 'Not Found',
                'detail' => 'Location not found.',
                'status' => 404,
            ], 404);
        }

        $location->load([
            'pickupPoints' => fn ($q) => $q->active(),
            'deliveryZones' => fn ($q) => $q->active(),
        ]);

        return response()->json([
            'data' => new LocationResource($location),
        ]);
    }

    public function pickupPoints(Location $location): JsonResponse
    {
        if (! $location->isActive()) {
            return response()->json([
                'title' => 'Not Found',
                'detail' => 'Location not found.',
                'status' => 404,
            ], 404);
        }

        $pickupPoints = $location->pickupPoints()->active()->orderBy('name')->get();

        return response()->json([
            'data' => PickupPointResource::collection($pickupPoints),
            'meta' => [
                'total' => $pickupPoints->count(),
            ],
        ]);
    }

    public function deliveryQuote(DeliveryQuoteRequest $request, Location $location): JsonResponse
    {
        if (! $location->isActive()) {
            return response()->json([
                'title' => 'Not Found',
                'detail' => 'Location not found.',
                'status' => 404,
            ], 404);
        }

        $validated = $request->validated();
        $lat = (float) $validated['lat'];
        $lng = (float) $validated['lng'];
        $orderSubtotal = $validated['order_subtotal'];

        $zone = $this->geofenceService->findMatchingZone($location->id, $lat, $lng);

        if ($zone === null) {
            $quote = $this->pricingService->notServiceableQuote();

            return response()->json([
                'data' => new DeliveryQuoteResource($quote),
            ]);
        }

        $quote = $this->pricingService->quote($location->id, $zone->id, $orderSubtotal);

        return response()->json([
            'data' => new DeliveryQuoteResource($quote),
        ]);
    }
}
