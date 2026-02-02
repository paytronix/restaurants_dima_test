<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FulfillmentValidationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'valid' => $this->resource['valid'],
            'normalized_requested_at' => $this->resource['normalized_requested_at'] ?? null,
            'earliest_possible_at' => $this->resource['earliest_possible_at'] ?? null,
            'reason' => $this->resource['reason'],
        ];
    }
}
