<?php

namespace App\Http\Requests\Calendar;

use Illuminate\Foundation\Http\FormRequest;

class CalendarRangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'fulfillment_type' => ['nullable', 'string', 'in:pickup,delivery'],
        ];
    }

    public function messages(): array
    {
        return [
            'from.required' => 'The from date is required.',
            'from.date_format' => 'The from date must be in YYYY-MM-DD format.',
            'to.required' => 'The to date is required.',
            'to.date_format' => 'The to date must be in YYYY-MM-DD format.',
            'to.after_or_equal' => 'The to date must be after or equal to the from date.',
            'fulfillment_type.in' => 'The fulfillment type must be pickup or delivery.',
        ];
    }
}
