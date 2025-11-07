<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModifierOptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'modifier_id' => $this->modifier_id,
            'name' => $this->name,
            'price_delta' => $this->price_delta,
            'is_active' => $this->is_active,
            'position' => $this->position,
        ];
    }
}
