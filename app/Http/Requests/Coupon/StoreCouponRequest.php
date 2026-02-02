<?php

namespace App\Http\Requests\Coupon;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:coupons,code'],
            'name' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['required', Rule::in(['percent', 'fixed'])],
            'discount_value' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'currency' => ['nullable', 'string', 'size:3', 'required_if:discount_type,fixed'],
            'starts_at' => ['nullable', 'date', 'after_or_equal:today'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'min_subtotal' => ['nullable', 'integer', 'min:0'],
            'max_uses_total' => ['nullable', 'integer', 'min:1'],
            'max_uses_per_customer' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Coupon code is required',
            'code.unique' => 'This coupon code already exists',
            'discount_type.required' => 'Discount type is required',
            'discount_type.in' => 'Discount type must be either percent or fixed',
            'discount_value.required' => 'Discount value is required',
            'discount_value.numeric' => 'Discount value must be a number',
            'discount_value.min' => 'Discount value must be at least 0',
            'currency.required_if' => 'Currency is required for fixed discount type',
            'currency.size' => 'Currency must be a 3-character code',
            'ends_at.after' => 'End date must be after start date',
        ];
    }
}
