<?php

namespace App\Http\Requests\Coupon;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $couponId = $this->route('coupon');

        return [
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('coupons', 'code')->ignore($couponId)],
            'name' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['sometimes', Rule::in(['percent', 'fixed'])],
            'discount_value' => ['sometimes', 'numeric', 'min:0', 'max:999999999999.99'],
            'currency' => ['nullable', 'string', 'size:3'],
            'starts_at' => ['nullable', 'date'],
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
            'code.unique' => 'This coupon code already exists',
            'discount_type.in' => 'Discount type must be either percent or fixed',
            'discount_value.numeric' => 'Discount value must be a number',
            'discount_value.min' => 'Discount value must be at least 0',
            'currency.size' => 'Currency must be a 3-character code',
            'ends_at.after' => 'End date must be after start date',
        ];
    }
}
