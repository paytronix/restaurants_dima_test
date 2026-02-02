<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CalendarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'location_id' => $this->resource['location_id'],
            'timezone' => $this->resource['timezone'],
            'from' => $this->resource['from'],
            'to' => $this->resource['to'],
            'days' => array_map(
                fn ($day) => (new CalendarDayResource($day))->toArray($request),
                $this->resource['days']
            ),
        ];
    }
}
