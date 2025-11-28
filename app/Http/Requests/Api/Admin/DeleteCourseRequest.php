<?php

namespace App\Http\Requests\Api\Admin;

use App\Models\CourseUser;
use App\Models\ManageStudentRecord;
use App\Models\CoursePrice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\DB;

class DeleteCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $courseId = $this->route('course');
        
        return [
            // Course ID is validated through route model binding
            // Additional validation in withValidator
        ];
    }

    public function messages(): array
    {
        return [
            'course.exists' => 'The course does not exist.',
            'course.has_students' => 'Cannot delete course. One or more students are assigned to this course.',
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $courseId = $this->route('course');
            
            // Check if course exists
            $course = \App\Models\Course::find($courseId);
            
            if (!$course) {
                $validator->errors()->add('course', 'The course does not exist.');
                return;
            }
            
            // Check for student assignments via ManageStudentRecord
            $hasAssignedStudents = ManageStudentRecord::where('course_id', $courseId)
                ->exists();
            
            // Check for parent purchases via CourseUser
            $hasPurchases = CourseUser::where('course_id', $courseId)
                ->exists();
            
            // Check for active subscriptions
            $coursePriceIds = CoursePrice::where('course_id', $courseId)
                ->whereNotNull('stripe_price_id')
                ->pluck('stripe_price_id')
                ->toArray();
            
            $hasActiveSubscriptions = false;
            if (!empty($coursePriceIds)) {
                // Check subscriptions table
                $hasActiveSubscriptions = DB::table('subscriptions')
                    ->where('stripe_status', config('constants.active_status'))
                    ->whereIn('stripe_price', $coursePriceIds)
                    ->exists();
                
                // Also check subscription_items table if not found in subscriptions
                if (!$hasActiveSubscriptions) {
                    $hasActiveSubscriptions = DB::table('subscription_items')
                        ->join('subscriptions', 'subscription_items.subscription_id', '=', 'subscriptions.id')
                        ->where('subscriptions.stripe_status', config('constants.active_status'))
                        ->whereIn('subscription_items.stripe_price', $coursePriceIds)
                        ->exists();
                }
            }
            
            // Build error message based on which conditions are true
            $errors = [];
            if ($hasAssignedStudents) {
                $errors[] = 'One or more students are assigned to this course';
            }
            if ($hasPurchases) {
                $errors[] = 'One or more parents have purchased this course';
            }
            if ($hasActiveSubscriptions) {
                $errors[] = 'This course has active subscriptions';
            }
            
            // Add error if any condition is true
            if (!empty($errors)) {
                $errorMessage = 'Cannot delete course. ' . implode(', ', $errors) . '.';
                $validator->errors()->add('course', $errorMessage);
            }
        });
    }
}

