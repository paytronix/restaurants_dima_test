<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateModifierOptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'price_delta' => 'sometimes|numeric|regex:/^-?\d+(\.\d{1,2})?$/',
            'is_active' => 'sometimes|boolean',
            'position' => 'sometimes|integer|min:0',
        ];
    }
}
