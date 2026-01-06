<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadTimeSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'location_id' => $this->location_id,
            'pickup_lead_time_min' => $this->pickup_lead_time_min,
            'delivery_lead_time_min' => $this->delivery_lead_time_min,
            'zone_extra_time_min' => $this->zone_extra_time_min,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
