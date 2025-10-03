<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'special_instructions' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'menu_item_id.required' => 'Menu item is required',
            'menu_item_id.exists' => 'Menu item does not exist',
            'quantity.required' => 'Quantity is required',
            'quantity.min' => 'Quantity must be at least 1',
            'quantity.max' => 'Quantity cannot exceed 99',
        ];
    }
}
