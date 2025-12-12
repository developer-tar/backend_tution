<?php

namespace App\Http\Requests\Api\Parent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaperPurchaseListRequest extends FormRequest
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
        $paymentStatuses = array_values(config('constants.stripe_payment_status'));
        
        return [
            'status' => ['nullable', 'string', Rule::in($paymentStatuses)],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'status.string' => 'Status must be a string.',
            'status.in' => 'Invalid payment status. Allowed values: ' . implode(', ', array_values(config('constants.stripe_payment_status'))),
        ];
    }
}

