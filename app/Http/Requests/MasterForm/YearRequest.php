<?php

namespace App\Http\Requests\MasterForm;

class YearRequest extends BaseMasterFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'name' => 'required|integer|min:1900|max:2100',
        ];

        // For updates, make fields optional
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['name'] = 'sometimes|integer|min:1900|max:2100';
        }

        return $rules;
    }
}
