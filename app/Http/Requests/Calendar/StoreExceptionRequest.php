<?php

namespace App\Http\Requests\Calendar;

use Illuminate\Foundation\Http\FormRequest;

class StoreExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'type' => ['required', 'string', 'in:closed_all_day,open_custom,blackout_window'],
            'open_time' => ['nullable', 'required_if:type,open_custom,blackout_window', 'date_format:H:i'],
            'close_time' => ['nullable', 'required_if:type,open_custom,blackout_window', 'date_format:H:i', 'after:open_time'],
            'fulfillment_type' => ['nullable', 'string', 'in:pickup,delivery,both'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'The date is required.',
            'date.date_format' => 'The date must be in YYYY-MM-DD format.',
            'type.required' => 'The exception type is required.',
            'type.in' => 'The type must be closed_all_day, open_custom, or blackout_window.',
            'open_time.required_if' => 'The open_time is required for open_custom and blackout_window types.',
            'open_time.date_format' => 'The open_time must be in HH:MM format.',
            'close_time.required_if' => 'The close_time is required for open_custom and blackout_window types.',
            'close_time.date_format' => 'The close_time must be in HH:MM format.',
            'close_time.after' => 'The close_time must be after the open_time.',
            'fulfillment_type.in' => 'The fulfillment_type must be pickup, delivery, or both.',
            'reason.max' => 'The reason may not be greater than 255 characters.',
        ];
    }
}
