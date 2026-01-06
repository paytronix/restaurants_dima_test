<?php

namespace App\Http\Requests\LeadTime;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadTimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pickup_lead_time_min' => 'sometimes|integer|min:0|max:240',
            'delivery_lead_time_min' => 'sometimes|integer|min:0|max:240',
            'zone_extra_time_min' => 'sometimes|integer|min:0|max:120',
        ];
    }
}
