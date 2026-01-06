<?php

namespace App\Http\Requests\PricingRule;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePricingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fee_amount' => 'sometimes|numeric|min:0|max:9999999999.99',
            'min_order_amount' => 'nullable|numeric|min:0|max:9999999999.99',
            'free_delivery_threshold' => 'nullable|numeric|min:0|max:9999999999.99',
            'currency' => 'sometimes|string|size:3',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $minOrder = $this->input('min_order_amount');
            $freeThreshold = $this->input('free_delivery_threshold');

            if ($minOrder === null && $freeThreshold === null) {
                return;
            }

            $rule = $this->route('pricingRule');
            $currentMinOrder = $rule ? $rule->min_order_amount : 0;
            $currentFreeThreshold = $rule ? $rule->free_delivery_threshold : null;

            $effectiveMinOrder = $minOrder !== null ? (float) $minOrder : (float) $currentMinOrder;
            $effectiveFreeThreshold = $freeThreshold !== null ? $freeThreshold : $currentFreeThreshold;

            if ($effectiveFreeThreshold !== null && (float) $effectiveFreeThreshold < $effectiveMinOrder) {
                $validator->errors()->add(
                    'free_delivery_threshold',
                    'The free delivery threshold must be greater than or equal to the minimum order amount.'
                );
            }
        });
    }
}
