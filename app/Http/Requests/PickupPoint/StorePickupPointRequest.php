<?php

namespace App\Http\Requests\PickupPoint;

use Illuminate\Foundation\Http\FormRequest;

class StorePickupPointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'status' => 'nullable|string|in:active,inactive',
            'address_line1' => 'required|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'country' => 'nullable|string|size:2',
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'instructions' => 'nullable|string|max:2000',
        ];
    }
}
