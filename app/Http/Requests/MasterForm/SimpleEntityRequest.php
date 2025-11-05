<?php

namespace App\Http\Requests\MasterForm;

class SimpleEntityRequest extends BaseMasterFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     * This handles: schools, roles, genders, regions, formats, target_schools, days, months, weekdays, locations, subjects
     */
    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'status' => 'nullable|integer|in:1,2,3', // 1=Pending, 2=Approved, 3=Rejected
        ];

        // For updates, make fields optional
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['name'] = 'sometimes|string|max:255';
            $rules['status'] = 'sometimes|integer|in:1,2,3';
        }

        return $rules;
    }
}
