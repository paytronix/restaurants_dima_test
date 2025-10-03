<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:20'],
            'pickup_time' => ['nullable', 'date', 'after:now'],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'special_instructions' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_name.required' => 'Customer name is required',
            'customer_email.required' => 'Customer email is required',
            'customer_email.email' => 'Customer email must be a valid email address',
            'customer_phone.required' => 'Customer phone is required',
            'pickup_time.after' => 'Pickup time must be in the future',
        ];
    }
}
