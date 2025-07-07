<?php

namespace App\Http\Requests\Api\Cart;

use Illuminate\Foundation\Http\FormRequest;

class RemoveFromCartRequest extends FormRequest
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
    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'id.required' => 'Cart item ID is required.',
            'id.exists' => 'The selected cart item does not exist.',
        ];
    }
}
