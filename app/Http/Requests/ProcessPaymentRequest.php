<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcessPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Payment amount is required',
            'amount.min' => 'Payment amount must be greater than 0',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->hasHeader('Idempotency-Key')) {
            $this->merge([
                '_missing_idempotency_key' => true,
            ]);
        }
    }
}
