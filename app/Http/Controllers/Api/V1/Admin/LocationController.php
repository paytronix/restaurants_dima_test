<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Location\StoreLocationRequest;
use App\Http\Requests\Location\UpdateLocationRequest;
use App\Http\Resources\LocationResource;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LocationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Location::with(['pickupPoints', 'deliveryZones']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $locations = $query->orderBy('name')->get();

        return response()->json([
            'data' => LocationResource::collection($locations),
            'meta' => [
                'total' => $locations->count(),
            ],
        ]);
    }

    public function store(StoreLocationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $location = Location::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'status' => $validated['status'] ?? Location::STATUS_ACTIVE,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address_line1' => $validated['address_line1'],
            'address_line2' => $validated['address_line2'] ?? null,
            'city' => $validated['city'],
            'postal_code' => $validated['postal_code'],
            'country' => $validated['country'] ?? 'PL',
            'lat' => $validated['lat'],
            'lng' => $validated['lng'],
        ]);

        return response()->json([
            'data' => new LocationResource($location),
        ], 201);
    }

    public function show(Location $location): JsonResponse
    {
        $location->load(['pickupPoints', 'deliveryZones.pricingRule', 'leadTimeSetting']);

        return response()->json([
            'data' => new LocationResource($location),
        ]);
    }

    public function update(UpdateLocationRequest $request, Location $location): JsonResponse
    {
        $validated = $request->validated();

        $location->update($validated);

        return response()->json([
            'data' => new LocationResource($location->fresh()),
        ]);
    }

    public function destroy(Location $location): Response
    {
        $location->update(['status' => Location::STATUS_INACTIVE]);

        return response()->noContent();
    }
}
