<?php

namespace App\Http\Requests\Calendar;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWeeklyHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hours' => ['required', 'array'],
            'hours.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'hours.*.open_time' => ['required', 'date_format:H:i'],
            'hours.*.close_time' => ['required', 'date_format:H:i', 'after:hours.*.open_time'],
            'hours.*.fulfillment_type' => ['required', 'string', 'in:pickup,delivery,both'],
            'hours.*.is_closed' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'hours.required' => 'The hours array is required.',
            'hours.array' => 'The hours must be an array.',
            'hours.*.day_of_week.required' => 'Each hour entry must have a day_of_week.',
            'hours.*.day_of_week.between' => 'The day_of_week must be between 0 (Sunday) and 6 (Saturday).',
            'hours.*.open_time.required' => 'Each hour entry must have an open_time.',
            'hours.*.open_time.date_format' => 'The open_time must be in HH:MM format.',
            'hours.*.close_time.required' => 'Each hour entry must have a close_time.',
            'hours.*.close_time.date_format' => 'The close_time must be in HH:MM format.',
            'hours.*.close_time.after' => 'The close_time must be after the open_time.',
            'hours.*.fulfillment_type.required' => 'Each hour entry must have a fulfillment_type.',
            'hours.*.fulfillment_type.in' => 'The fulfillment_type must be pickup, delivery, or both.',
        ];
    }
}
