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


        $rules = [
            'product_type' => ['required', Rule::in(array_keys($tableMap))],
            'product_id' => ['required', "exists:{$productTable},id"],
            'quantity' => 'required|integer|min:1',
        ];
        // if ($productType === 'course') {
        //     $rules['price_id'] = ['required', 'exists:course_prices,stripe_price_id'];
        // }
        return $rules;
    }
    public function messages(): array
    {
        // Build list for friendly error text
        $types = implode(', ', array_keys(config('constants.table_map', [])));

        return [
            'product_type.required' => 'Product type is required.',
            'product_type.in' => "Product type must be one of: {$types}.",

            'product_id.required' => 'Product ID is required.',
            'product_id.exists' => 'The selected product does not exist.',

            'quantity.required' => 'Quantity is required.',
            'quantity.integer' => 'Quantity must be an integer.',
            'quantity.min' => 'Quantity must be at least 1.',

            'price_id.required' => 'Price id  is required for courses.',
            'price_id.exists' => 'The selected price is invalid.',
        ];
    }
}
