<?php

namespace App\Http\Requests\MasterForm;

use App\Models\Week;
use App\Models\AcdemicYear;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

class WeekRequest extends BaseMasterFormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        // Clean up date formats - remove extra spaces
        if ($this->has('start_date')) {
            $startDate = $this->input('start_date');
            // Fix format like "1998-11-01T00:42 00:00:00" to "1998-11-01T00:42:00"
            $startDate = preg_replace('/T(\d{2}:\d{2})\s+(\d{2}:\d{2}:\d{2})/', 'T$1:00', $startDate);
            $this->merge(['start_date' => $startDate]);
        }
        
        if ($this->has('end_date')) {
            $endDate = $this->input('end_date');
            // Fix format like "1998-11-10T07:04 23:59:59" to "1998-11-10T23:59:59"
            $endDate = preg_replace('/T(\d{2}:\d{2})\s+(\d{2}:\d{2}:\d{2})/', 'T$2', $endDate);
            $this->merge(['end_date' => $endDate]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $weekId = $this->route('id'); // Get week ID for updates
        
        $rules = [
            'academic_year_id' => [
                'required',
                'integer',
                'exists:acdemic_years,id',
                function ($attribute, $value, $fail) {
                    $this->validateAcademicYearAlignment($attribute, $value, $fail);
                }
            ],
            'week_number' => [
                'required',
                'integer',
                'min:1',
                'max:52',
                Rule::unique('weeks', 'week_number')
                    ->where('academic_year_id', $this->academic_year_id)
                    ->ignore($weekId)
            ],
            'start_date' => [
                'required',
                'date',
                function ($attribute, $value, $fail) {
                    $this->validateDateAlignment($attribute, $value, $fail);
                }
            ],
            'end_date' => [
                'required',
                'date',
                'after:start_date',
                function ($attribute, $value, $fail) {
                    $this->validateDateAlignment($attribute, $value, $fail);
                    $this->validateNoOverlapping($attribute, $value, $fail);
                }
            ],
        ];

        // For updates, make fields optional but keep validation logic
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules = [
                'academic_year_id' => [
                    'sometimes',
                    'exists:acdemic_years,id',
                    function ($attribute, $value, $fail) {
                        $this->validateAcademicYearAlignment($attribute, $value, $fail);
                    }
                ],
                'week_number' => [
                    'sometimes',
                    'integer',
                    'min:1',
                    'max:52',
                    Rule::unique('weeks', 'week_number')
                        ->where('academic_year_id', $this->academic_year_id ?? $this->getExistingAcademicYearId($weekId))
                        ->ignore($weekId)
                ],
                'start_date' => [
                    'sometimes',
                    'date',
                    function ($attribute, $value, $fail) {
                        $this->validateDateAlignment($attribute, $value, $fail);
                    }
                ],
                'end_date' => [
                    'sometimes',
                    'date',
                    function ($attribute, $value, $fail) {
                        $this->validateDateAlignment($attribute, $value, $fail);
                        $this->validateNoOverlapping($attribute, $value, $fail);
                    }
                ],
            ];

            // Add conditional validation for end_date if start_date is present
            if ($this->has('start_date')) {
                $rules['end_date'][] = 'after:start_date';
            }
        }

        return $rules;
    }

    /**
     * Validate that dates align with the academic year
     */
    private function validateAcademicYearAlignment($attribute, $value, $fail)
    {
        if ($attribute === 'academic_year_id') {
            return; // Skip for academic_year_id itself
        }

        $academicYearId = $this->academic_year_id ?? $this->getExistingAcademicYearId($this->route('id'));
        
        if (!$academicYearId) {
            return; // Let the exists validation handle missing academic year
        }

        $academicYear = AcdemicYear::find($academicYearId);
        if (!$academicYear) {
            return;
        }

        $date = Carbon::parse($value);
        $yearStart = Carbon::create($academicYear->start_year, 1, 1)->startOfYear();
        $yearEnd = Carbon::create($academicYear->start_year, 12, 31)->endOfYear();

        if ($date->lt($yearStart) || $date->gt($yearEnd)) {
            $fail("The {$attribute} must be within the academic year {$academicYear->start_year}-{$academicYear->end_year}.");
        }
    }

    /**
     * Validate that dates follow the week structure (Monday to Sunday)
     */
    private function validateDateAlignment($attribute, $value, $fail)
    {
        $date = Carbon::parse($value);
        
        if ($attribute === 'start_date') {
            // Start date should be a Monday
            if ($date->dayOfWeek !== Carbon::MONDAY) {
                $fail('The start date must be a Monday to align with the week structure.');
            }
        }
        
        if ($attribute === 'end_date') {
            // End date should be a Sunday at 22:00
            if ($date->dayOfWeek !== Carbon::SUNDAY) {
                $fail('The end date must be a Sunday to align with the week structure.');
            }
            
            if ($date->format('H:i:s') !== '22:00:00') {
                $fail('The end date must be at 22:00:00 (10:00 PM) to follow the standard week format.');
            }
        }
    }

    /**
     * Validate that week dates don't overlap with existing weeks
     */
    private function validateNoOverlapping($attribute, $value, $fail)
    {
        $academicYearId = $this->academic_year_id ?? $this->getExistingAcademicYearId($this->route('id'));
        $startDate = $this->start_date;
        $endDate = $this->end_date;
        $weekId = $this->route('id');

        if (!$academicYearId || !$startDate || !$endDate) {
            return; // Skip if required data is missing
        }

        $query = Week::where('academic_year_id', $academicYearId)
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                  ->orWhereBetween('end_date', [$startDate, $endDate])
                  ->orWhere(function ($q2) use ($startDate, $endDate) {
                      $q2->where('start_date', '<=', $startDate)
                         ->where('end_date', '>=', $endDate);
                  });
            });

        // Exclude current week for updates
        if ($weekId) {
            $query->where('id', '!=', $weekId);
        }

        $overlappingWeeks = $query->get();

        if ($overlappingWeeks->count() > 0) {
            $weekNumbers = $overlappingWeeks->pluck('week_number')->join(', ');
            $fail("The date range overlaps with existing week(s): {$weekNumbers}. Week dates must not overlap.");
        }
    }

    /**
     * Get existing academic year ID for updates
     */
    private function getExistingAcademicYearId($weekId)
    {
        if (!$weekId) {
            return null;
        }

        $week = Week::find($weekId);
        return $week ? $week->academic_year_id : null;
    }

    /**
     * Custom validation messages
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'academic_year_id.required' => 'The academic year is required.',
            'academic_year_id.integer' => 'The academic year must be a valid number.',
            'academic_year_id.exists' => 'The selected academic year does not exist.',
            'week_number.required' => 'The week number is required.',
            'week_number.integer' => 'The week number must be a valid number (1-52).',
            'week_number.min' => 'The week number must be at least 1.',
            'week_number.max' => 'The week number must not be greater than 52.',
            'week_number.unique' => 'This week number already exists for the selected academic year.',
            'start_date.required' => 'The start date is required.',
            'start_date.date' => 'The start date must be a valid date format (YYYY-MM-DD or YYYY-MM-DDTHH:MM:SS).',
            'start_date.after' => 'The start date must be before the end date.',
            'end_date.required' => 'The end date is required.',
            'end_date.date' => 'The end date must be a valid date format (YYYY-MM-DD or YYYY-MM-DDTHH:MM:SS).',
            'end_date.after' => 'The end date must be after the start date.',
        ]);
    }
}
