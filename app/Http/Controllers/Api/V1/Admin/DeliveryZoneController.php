<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeliveryZone\StoreDeliveryZoneRequest;
use App\Http\Requests\DeliveryZone\UpdateDeliveryZoneRequest;
use App\Http\Resources\DeliveryZoneResource;
use App\Models\DeliveryZone;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DeliveryZoneController extends Controller
{
    public function store(StoreDeliveryZoneRequest $request, Location $location): JsonResponse
    {
        $validated = $request->validated();

        $deliveryZone = DeliveryZone::create([
            'location_id' => $location->id,
            'name' => $validated['name'],
            'status' => $validated['status'] ?? DeliveryZone::STATUS_ACTIVE,
            'polygon_geojson' => $validated['polygon_geojson'],
            'priority' => $validated['priority'] ?? 0,
        ]);

        return response()->json([
            'data' => new DeliveryZoneResource($deliveryZone),
        ], 201);
    }

    public function update(UpdateDeliveryZoneRequest $request, DeliveryZone $deliveryZone): JsonResponse
    {
        $validated = $request->validated();

        $deliveryZone->update($validated);

        return response()->json([
            'data' => new DeliveryZoneResource($deliveryZone->fresh()->load('pricingRule')),
        ]);
    }

    public function destroy(DeliveryZone $deliveryZone): Response
    {
        $deliveryZone->delete();

        return response()->noContent();
    }
}
