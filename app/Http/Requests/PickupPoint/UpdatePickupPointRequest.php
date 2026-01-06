<?php

namespace App\Http\Requests\PickupPoint;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePickupPointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'status' => 'sometimes|string|in:active,inactive',
            'address_line1' => 'sometimes|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'sometimes|string|max:100',
            'postal_code' => 'sometimes|string|max:20',
            'country' => 'sometimes|string|size:2',
            'lat' => 'sometimes|numeric|between:-90,90',
            'lng' => 'sometimes|numeric|between:-180,180',
            'instructions' => 'nullable|string|max:2000',
        ];
    }
}
