<?php

namespace App\Http\Requests\Calendar;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'type' => ['nullable', 'string', 'in:closed_all_day,open_custom,blackout_window'],
            'open_time' => ['nullable', 'date_format:H:i'],
            'close_time' => ['nullable', 'date_format:H:i'],
            'fulfillment_type' => ['nullable', 'string', 'in:pickup,delivery,both'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.date_format' => 'The date must be in YYYY-MM-DD format.',
            'type.in' => 'The type must be closed_all_day, open_custom, or blackout_window.',
            'open_time.date_format' => 'The open_time must be in HH:MM format.',
            'close_time.date_format' => 'The close_time must be in HH:MM format.',
            'fulfillment_type.in' => 'The fulfillment_type must be pickup, delivery, or both.',
            'reason.max' => 'The reason may not be greater than 255 characters.',
        ];
    }
}
