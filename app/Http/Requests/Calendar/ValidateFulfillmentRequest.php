<?php

namespace App\Http\Requests\Calendar;

use Illuminate\Foundation\Http\FormRequest;

class ValidateFulfillmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fulfillment_type' => ['required', 'string', 'in:pickup,delivery'],
            'requested_at' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'fulfillment_type.required' => 'The fulfillment type is required.',
            'fulfillment_type.in' => 'The fulfillment type must be pickup or delivery.',
            'requested_at.required' => 'The requested fulfillment time is required.',
            'requested_at.date' => 'The requested fulfillment time must be a valid date.',
        ];
    }
}
