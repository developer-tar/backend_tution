<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class FetchAcdemicCourseList extends FormRequest
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
            'acdemic_course_id' => $this->route('acdemic_course_id'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
      
        return [
            'acdemic_course_id' => ['required', 'integer', 'exists:acdemic_course,id'],
        ];
    }
}