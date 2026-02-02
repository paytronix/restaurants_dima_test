<?php

namespace App\Http\Requests\Calendar;

use Illuminate\Foundation\Http\FormRequest;

class SlotsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'fulfillment_type' => ['required', 'string', 'in:pickup,delivery'],
            'now' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'The date is required.',
            'date.date_format' => 'The date must be in YYYY-MM-DD format.',
            'fulfillment_type.required' => 'The fulfillment type is required.',
            'fulfillment_type.in' => 'The fulfillment type must be pickup or delivery.',
            'now.date' => 'The now parameter must be a valid date.',
        ];
    }
}
