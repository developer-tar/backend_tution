<?php

namespace App\Http\Requests\Api\Admin;

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
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'payment_status' => ['nullable', 'string', Rule::in($paymentStatuses)],
            'date_from' => ['nullable', 'date', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'sort_by' => ['nullable', 'string', Rule::in(['latest_purchase_date', 'total_amount', 'total_papers', 'parent_name'])],
            'sort_order' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
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
            'search.string' => 'Search parameter must be a string.',
            'search.max' => 'Search parameter cannot exceed 255 characters.',
            'page.integer' => 'Page must be a valid integer.',
            'page.min' => 'Page must be at least 1.',
            'per_page.integer' => 'Per page must be a valid integer.',
            'per_page.min' => 'Per page must be at least 1.',
            'per_page.max' => 'Per page cannot exceed 100.',
            'payment_status.string' => 'Payment status must be a string.',
            'payment_status.in' => 'Invalid payment status. Allowed values: ' . implode(', ', array_values(config('constants.stripe_payment_status'))),
            'date_from.date' => 'Date from must be a valid date.',
            'date_from.date_format' => 'Date from must be in Y-m-d format.',
            'date_to.date' => 'Date to must be a valid date.',
            'date_to.date_format' => 'Date to must be in Y-m-d format.',
            'date_to.after_or_equal' => 'Date to must be after or equal to date from.',
            'sort_by.string' => 'Sort by must be a string.',
            'sort_by.in' => 'Invalid sort field. Allowed values: latest_purchase_date, total_amount, total_papers, parent_name.',
            'sort_order.string' => 'Sort order must be a string.',
            'sort_order.in' => 'Sort order must be either asc or desc.',
        ];
    }
}

