<?php

namespace App\Http\Requests\MasterForm;

class AcademicYearRequest extends BaseMasterFormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        // Handle both academic_year and start_end_year formats like "2024/2025"
        $yearField = $this->input('academic_year') ?: $this->input('start_end_year');
        
        if ($yearField) {
            // Parse the academic year format and add to request data
            if (preg_match('/^(\d{4})\/(\d{4})$/', $yearField, $matches)) {
                $startYear = (int) $matches[1];
                $endYear = (int) $matches[2];
                
                // Merge the parsed years into the request for the controller
                $this->merge([
                    'start_year' => $startYear,
                    'end_year' => $endYear,
                ]);
                
                // Also set academic_year for consistency
                if (!$this->input('academic_year')) {
                    $this->merge(['academic_year' => $yearField]);
                }
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        // Handle academic_year or start_end_year format like "2024/2025"
        if ($this->input('academic_year') || $this->input('start_end_year')) {
            $yearValidation = [
                'required',
                'string',
                'regex:/^\d{4}\/\d{4}$/',
                function ($attribute, $value, $fail) {
                    if (preg_match('/^(\d{4})\/(\d{4})$/', $value, $matches)) {
                        $startYear = (int) $matches[1];
                        $endYear = (int) $matches[2];
                        
                        if ($startYear < 1900 || $startYear > 2100) {
                            $fail('The start year must be between 1900 and 2100.');
                        }
                        
                        if ($endYear < 1900 || $endYear > 2100) {
                            $fail('The end year must be between 1900 and 2100.');
                        }
                        
                        if ($endYear <= $startYear) {
                            $fail('The end year must be greater than the start year.');
                        }
                        
                        // Ensure it's consecutive years
                        if ($endYear !== $startYear + 1) {
                            $fail('The academic year must be consecutive years (e.g., 2024/2025).');
                        }
                    } else {
                        $fail('The academic year format must be YYYY/YYYY (e.g., 2024/2025).');
                    }
                },
            ];
            
            $rules = ['status' => 'nullable|integer|in:1,2,3'];
            
            // Add validation for whichever field is present
            if ($this->input('academic_year')) {
                $rules['academic_year'] = $yearValidation;
            }
            
            if ($this->input('start_end_year')) {
                $rules['start_end_year'] = $yearValidation;
            }
            
            return $rules;
        }

        // Traditional separate fields validation
        $rules = [
            'start_year' => 'required|integer|min:1900|max:2100',
            'end_year' => 'required|integer|min:1900|max:2100|gt:start_year',
            'status' => 'nullable|integer|in:1,2,3',
        ];

        // For updates, make fields optional
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules = [
                'start_year' => 'sometimes|integer|min:1900|max:2100',
                'end_year' => 'sometimes|integer|min:1900|max:2100',
                'status' => 'sometimes|integer|in:1,2,3',
            ];

            // Add conditional validation for end_year if start_year is present
            if ($this->has('start_year')) {
                $rules['end_year'] = 'sometimes|integer|min:1900|max:2100|gt:start_year';
            }
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'academic_year.required' => 'The academic year is required.',
            'academic_year.regex' => 'The academic year format must be YYYY/YYYY (e.g., 2024/2025).',
            'start_end_year.required' => 'The academic year is required.',
            'start_end_year.regex' => 'The academic year format must be YYYY/YYYY (e.g., 2024/2025).',
            'start_year.required' => 'The start year is required.',
            'start_year.integer' => 'The start year must be a valid year.',
            'start_year.min' => 'The start year must be at least 1900.',
            'start_year.max' => 'The start year must not be greater than 2100.',
            'end_year.required' => 'The end year is required.',
            'end_year.integer' => 'The end year must be a valid year.',
            'end_year.min' => 'The end year must be at least 1900.',
            'end_year.max' => 'The end year must not be greater than 2100.',
            'end_year.gt' => 'The end year must be greater than the start year.',
            'status.integer' => 'The status must be a number.',
            'status.in' => 'The status must be 1 (Pending), 2 (Approved), or 3 (Rejected).',
        ];
    }
}
