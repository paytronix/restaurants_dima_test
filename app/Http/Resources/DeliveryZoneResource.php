<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryZoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'location_id' => $this->location_id,
            'name' => $this->name,
            'status' => $this->status,
            'polygon_geojson' => $this->polygon_geojson,
            'priority' => $this->priority,
            'pricing_rule' => $this->when(
                $this->relationLoaded('pricingRule') && $this->pricingRule !== null,
                fn () => new DeliveryPricingRuleResource($this->pricingRule)
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
