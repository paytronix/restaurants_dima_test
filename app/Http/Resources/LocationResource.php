<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status,
            'phone' => $this->phone,
            'email' => $this->email,
            'address_line1' => $this->address_line1,
            'address_line2' => $this->address_line2,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'pickup_points_count' => $this->when(
                $this->relationLoaded('pickupPoints'),
                fn () => $this->pickupPoints->where('status', 'active')->count()
            ),
            'delivery_zones_count' => $this->when(
                $this->relationLoaded('deliveryZones'),
                fn () => $this->deliveryZones->where('status', 'active')->count()
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
