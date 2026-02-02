<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SlotsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'location_id' => $this->resource['location_id'],
            'date' => $this->resource['date'],
            'timezone' => $this->resource['timezone'],
            'fulfillment_type' => $this->resource['fulfillment_type'],
            'slots' => array_map(
                fn ($slot) => (new SlotResource($slot))->toArray($request),
                $this->resource['slots']
            ),
        ];

        if (isset($this->resource['error'])) {
            $data['error'] = $this->resource['error'];
        }

        if (isset($this->resource['reason'])) {
            $data['reason'] = $this->resource['reason'];
        }

        return $data;
    }
}
