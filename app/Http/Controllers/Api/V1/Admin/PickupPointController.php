<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PickupPoint\StorePickupPointRequest;
use App\Http\Requests\PickupPoint\UpdatePickupPointRequest;
use App\Http\Resources\PickupPointResource;
use App\Models\Location;
use App\Models\PickupPoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PickupPointController extends Controller
{
    public function store(StorePickupPointRequest $request, Location $location): JsonResponse
    {
        $validated = $request->validated();

        $pickupPoint = PickupPoint::create([
            'location_id' => $location->id,
            'name' => $validated['name'],
            'status' => $validated['status'] ?? PickupPoint::STATUS_ACTIVE,
            'address_line1' => $validated['address_line1'],
            'address_line2' => $validated['address_line2'] ?? null,
            'city' => $validated['city'],
            'postal_code' => $validated['postal_code'],
            'country' => $validated['country'] ?? 'PL',
            'lat' => $validated['lat'],
            'lng' => $validated['lng'],
            'instructions' => $validated['instructions'] ?? null,
        ]);

        return response()->json([
            'data' => new PickupPointResource($pickupPoint),
        ], 201);
    }

    public function update(UpdatePickupPointRequest $request, PickupPoint $pickupPoint): JsonResponse
    {
        $validated = $request->validated();

        $pickupPoint->update($validated);

        return response()->json([
            'data' => new PickupPointResource($pickupPoint->fresh()),
        ]);
    }

    public function destroy(PickupPoint $pickupPoint): Response
    {
        $pickupPoint->delete();

        return response()->noContent();
    }
}
