<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CalendarDayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'date' => $this->resource['date'],
            'day_of_week' => $this->resource['day_of_week'],
            'day_name' => $this->resource['day_name'],
            'is_open' => $this->resource['is_open'],
            'reason' => $this->resource['reason'],
            'hours' => $this->resource['hours'],
            'exceptions' => $this->resource['exceptions'] ?? [],
        ];
    }
}
