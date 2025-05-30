<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class FetchWeeksRequestList extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {

        return true;
    }

    /**
     * Merge input parameter into validation data.
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'acdemic_course_id' => $this->input('acdemic_course_id'),
            'assigned_weeks' => $this->input('assigned_weeks'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'acdemic_course_id' => ['nullable', 'integer', 'exists:acdemic_course,id'],
            'assigned_weeks' => ['required', 'integer', 'in:0,1']
        ];
    }
}