<?php

namespace App\Http\Requests\Coupon;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCouponTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_type' => ['required', Rule::in(['category', 'menu_item'])],
            'target_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'target_type.required' => 'Target type is required',
            'target_type.in' => 'Target type must be either category or menu_item',
            'target_id.required' => 'Target ID is required',
            'target_id.integer' => 'Target ID must be an integer',
            'target_id.min' => 'Target ID must be at least 1',
        ];
    }
}
