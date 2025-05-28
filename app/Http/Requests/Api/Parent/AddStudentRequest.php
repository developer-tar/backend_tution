<?php

namespace App\Http\Requests\Api\Parent;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
class AddStudentRequest extends FormRequest
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

        return
            [
                'first_name' => 'required',
                'last_name' => 'required',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|min:8|max:10|confirmed',
                'password_confirmation' => 'required', 
                'year_id' => 'required|integer|exists:years,id',
                'month_id' => 'required|integer|exists:months,id',
                'day_id' => 'required|integer|exists:days,id',
                'region_id' => 'required|integer|exists:regions,id',
                'gender_id' => 'required|integer|exists:genders,id',
                'target_school_id' => 'required|integer|exists:target_schools,id',
                'display_name' => 'required|string|max:100|unique:student_details,display_name',
                'show_answer_after_n_attempts' => 'nullable|integer|min:1|max:4',
                'allow_view_examiner_report_for_mocks' => 'nullable|boolean',
                'can_change_password' => 'nullable|boolean',
                'bio' => 'nullable|string',
            ];
    }
}
