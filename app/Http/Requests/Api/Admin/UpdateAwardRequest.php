<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAwardRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $awardId = $this->route('award') ?? $this->route('id');

        if ($awardId) {
            $this->merge([
                'award' => $awardId,
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'award' => [
                'required',
                'integer',
                Rule::exists('awards', 'id'),
            ],
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|string|max:255',
            'criteria' => 'nullable|string',
            'certificate_template' => 'nullable|string|max:255',
            'status' => 'nullable|integer|in:0,1',
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
            'award.required' => 'The award ID is required.',
            'award.integer' => 'The award ID must be an integer.',
            'award.exists' => 'The selected award does not exist.',
            'name.required' => 'The name field is required.',
            'name.string' => 'The name must be a string.',
            'name.max' => 'The name may not be greater than :max characters.',
            'description.string' => 'The description must be a string.',
            'type.string' => 'The type must be a string.',
            'type.max' => 'The type may not be greater than :max characters.',
            'criteria.string' => 'The criteria must be a string.',
            'certificate_template.string' => 'The certificate template must be a string.',
            'certificate_template.max' => 'The certificate template may not be greater than :max characters.',
            'status.integer' => 'The status must be an integer.',
            'status.in' => 'The status must be either 0 or 1.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'award' => 'award',
            'name' => 'name',
            'description' => 'description',
            'type' => 'type',
            'criteria' => 'criteria',
            'certificate_template' => 'certificate template',
            'status' => 'status',
        ];
    }
}
