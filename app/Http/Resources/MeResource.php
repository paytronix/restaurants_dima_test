<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->profile;

        return [
            'id' => $this->id,
            'email' => $this->email,
            'email_verified' => $this->email_verified_at !== null,
            'profile' => $profile !== null ? new CustomerProfileResource($profile) : null,
        ];
    }
}
