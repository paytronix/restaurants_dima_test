<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LocationHourResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'location_id' => $this->location_id,
            'day_of_week' => $this->day_of_week,
            'day_name' => $this->day_name,
            'open_time' => substr($this->open_time, 0, 5),
            'close_time' => substr($this->close_time, 0, 5),
            'fulfillment_type' => $this->fulfillment_type,
            'is_closed' => $this->is_closed,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
