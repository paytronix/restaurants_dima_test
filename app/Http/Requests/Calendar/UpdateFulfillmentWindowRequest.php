<?php

namespace App\Http\Requests\Calendar;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFulfillmentWindowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fulfillment_type' => ['required', 'string', 'in:pickup,delivery'],
            'slot_interval_min' => ['nullable', 'integer', 'min:5', 'max:120'],
            'slot_duration_min' => ['nullable', 'integer', 'min:5', 'max:120'],
            'min_lead_time_min' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'cutoff_min_before_close' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'max_days_ahead' => ['nullable', 'integer', 'min:1', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return [
            'fulfillment_type.required' => 'The fulfillment type is required.',
            'fulfillment_type.in' => 'The fulfillment type must be pickup or delivery.',
            'slot_interval_min.min' => 'The slot interval must be at least 5 minutes.',
            'slot_interval_min.max' => 'The slot interval may not be greater than 120 minutes.',
            'slot_duration_min.min' => 'The slot duration must be at least 5 minutes.',
            'slot_duration_min.max' => 'The slot duration may not be greater than 120 minutes.',
            'min_lead_time_min.min' => 'The minimum lead time must be at least 0 minutes.',
            'min_lead_time_min.max' => 'The minimum lead time may not be greater than 1440 minutes.',
            'cutoff_min_before_close.min' => 'The cutoff must be at least 0 minutes.',
            'cutoff_min_before_close.max' => 'The cutoff may not be greater than 1440 minutes.',
            'max_days_ahead.min' => 'The max days ahead must be at least 1.',
            'max_days_ahead.max' => 'The max days ahead may not be greater than 30.',
        ];
    }
}
