<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FulfillmentWindowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'location_id' => $this->location_id,
            'fulfillment_type' => $this->fulfillment_type,
            'slot_interval_min' => $this->slot_interval_min,
            'slot_duration_min' => $this->slot_duration_min,
            'min_lead_time_min' => $this->min_lead_time_min,
            'cutoff_min_before_close' => $this->cutoff_min_before_close,
            'max_days_ahead' => $this->max_days_ahead,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
