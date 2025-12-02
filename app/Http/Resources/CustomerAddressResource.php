<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerAddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'country' => $this->country,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'street_line1' => $this->street_line1,
            'street_line2' => $this->street_line2,
            'is_default' => $this->is_default,
        ];
    }
}
