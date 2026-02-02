<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LocationExceptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'location_id' => $this->location_id,
            'date' => $this->date->format('Y-m-d'),
            'type' => $this->type,
            'open_time' => $this->open_time ? substr($this->open_time, 0, 5) : null,
            'close_time' => $this->close_time ? substr($this->close_time, 0, 5) : null,
            'fulfillment_type' => $this->fulfillment_type,
            'reason' => $this->reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
