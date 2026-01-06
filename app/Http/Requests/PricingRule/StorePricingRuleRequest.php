<?php

namespace App\Http\Requests\PricingRule;

use Illuminate\Foundation\Http\FormRequest;

class StorePricingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fee_amount' => 'required|numeric|min:0|max:9999999999.99',
            'min_order_amount' => 'nullable|numeric|min:0|max:9999999999.99',
            'free_delivery_threshold' => 'nullable|numeric|min:0|max:9999999999.99',
            'currency' => 'nullable|string|size:3',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $minOrder = $this->input('min_order_amount', 0);
            $freeThreshold = $this->input('free_delivery_threshold');

            if ($freeThreshold !== null && (float) $freeThreshold < (float) $minOrder) {
                $validator->errors()->add(
                    'free_delivery_threshold',
                    'The free delivery threshold must be greater than or equal to the minimum order amount.'
                );
            }
        });
    }
}
