<?php

namespace App\Http\Requests\Api\Cart;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddToCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productType = $this->input('product_type');
    

        $tableMap = config('constants.table_map');
        $productTable = $tableMap[$productType] ?? 'courses';
       
        return [
            'product_type' => ['required', Rule::in(array_keys($tableMap))],
            'product_id' => ['required', "exists:{$productTable},id"],
            'quantity' => 'required|integer|min:1',
        ];
    }
    public function messages(): array
    {
        return [
            'product_type.required' => 'Product type is required.',
            'product_type.in' => 'Product type must be one of: course, mock, exam.',
            'product_id.required' => 'Product ID is required.',
            'product_id.exists' => 'The selected product does not exist.',
        ];
    }
}
