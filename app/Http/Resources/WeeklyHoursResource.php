<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeeklyHoursResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'location_id' => $this->resource['location_id'],
            'timezone' => $this->resource['timezone'],
            'weekly_hours' => $this->resource['weekly_hours'],
        ];
    }
}
