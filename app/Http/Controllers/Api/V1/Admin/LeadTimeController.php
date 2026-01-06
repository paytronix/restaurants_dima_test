<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\LeadTime\UpdateLeadTimeRequest;
use App\Http\Resources\LeadTimeSettingResource;
use App\Models\LeadTimeSetting;
use App\Models\Location;
use Illuminate\Http\JsonResponse;

class LeadTimeController extends Controller
{
    public function update(UpdateLeadTimeRequest $request, Location $location): JsonResponse
    {
        $validated = $request->validated();

        $setting = LeadTimeSetting::updateOrCreate(
            ['location_id' => $location->id],
            [
                'pickup_lead_time_min' => $validated['pickup_lead_time_min'] ?? 20,
                'delivery_lead_time_min' => $validated['delivery_lead_time_min'] ?? 45,
                'zone_extra_time_min' => $validated['zone_extra_time_min'] ?? 0,
            ]
        );

        return response()->json([
            'data' => new LeadTimeSettingResource($setting),
        ]);
    }
}
