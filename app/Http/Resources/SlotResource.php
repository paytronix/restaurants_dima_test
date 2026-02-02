<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SlotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slot_start' => $this->resource['slot_start'],
            'slot_end' => $this->resource['slot_end'],
            'is_orderable' => $this->resource['is_orderable'],
            'reason' => $this->resource['reason'],
        ];
    }
}
