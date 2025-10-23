<?php

namespace App\Http\Requests\Api\Parent;

use App\Models\StudentDetail;
use App\Services\ParentCourseService;
use Illuminate\Foundation\Http\FormRequest;

class FetchParentStudentsRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [];  
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
       
        $validator->after(function ($validator) {
            $parentId = auth()->user()->id;
            
            // Check if parent has any students
            $hasStudents = StudentDetail::where('parent_id', $parentId)->exists();
            
            if (!$hasStudents) {
                $validator->errors()->add('parent', 'You do not have any students registered.');
                return;
            }

            // Get parent's subscriptions using service
            $parentCourseService = app(ParentCourseService::class);
            $parentSubscriptions = $parentCourseService->getParentSubscriptions($parentId);

            if (empty($parentSubscriptions)) {
                $validator->errors()->add('subscriptions', 'You do not have any active subscriptions to assign courses.');
                return;
            }

            // Get available courses using service
            $availableCourses = $parentCourseService->getAvailableCourses($parentSubscriptions);

            if ($availableCourses->isEmpty()) {
                $validator->errors()->add('courses', 'No courses found for your subscriptions.');
                return;
            }

            // Store available courses in request for controller use
            $this->merge(['available_courses' => $availableCourses->toArray()]);
        });
    }


}
