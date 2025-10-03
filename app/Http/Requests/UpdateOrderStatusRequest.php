<?php

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(array_column(OrderStatus::cases(), 'value'))],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status is required',
            'status.in' => 'Invalid status value',
        ];
    }
}
