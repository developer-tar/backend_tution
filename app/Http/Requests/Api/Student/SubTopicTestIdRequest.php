<?php

namespace App\Http\Requests\Api\Student;

use App\Models\ManageStudentRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class SubTopicTestIdRequest extends FormRequest
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
            'sub_topic_test_id' => $this->route('sub_topic_test_id'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        
        return [
            'sub_topic_test_id' => [
                'required', 
                'integer', 
                'exists:course_tests,id',
                function ($attribute, $value, $fail) {
                    $record = ManageStudentRecord::where([
                        'model_type' => 'App\\Models\\CourseTest',
                        'model_id' => $value,
                        'buyer_id' => Auth::id()
                    ])->first();

                    if ($record && $record->is_completed == config('constants.completed.YES')) {
                        $fail('You have already completed this sub-topic test.');
                    }
                    
                    if ($record && $record->is_overdue) {
                        $fail('This sub-topic test is overdue and cannot be attempted.');
                    }
                }
            ],
        ];
    }
}
