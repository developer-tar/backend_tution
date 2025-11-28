<?php

namespace App\Http\Requests\Api\Admin;

use App\Models\CourseAssignment;
use App\Models\ManageStudentRecord;
use App\Models\CourseUser;
use App\Models\CoursePrice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\DB;

class DeleteAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Assignment ID is validated through route model binding
            // Additional validation in withValidator
        ];
    }

    public function messages(): array
    {
        return [
            'assignment.exists' => 'The assignment does not exist.',
            'assignment.has_students' => 'Cannot delete assignment. One or more students are assigned to this assignment.',
            'assignment.has_purchases' => 'Cannot delete assignment. One or more parents have purchased the course associated with this assignment.',
            'assignment.has_subscriptions' => 'Cannot delete assignment. One or more parents have active subscriptions for the course associated with this assignment.',
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $assignmentId = $this->route('assignment');
            
            // Check if assignment exists
            $assignment = CourseAssignment::find($assignmentId);
            
            if (!$assignment) {
                $validator->errors()->add('assignment', 'The assignment does not exist.');
                return;
            }
            
            // Check for student assignments via ManageStudentRecord
            $hasAssignedStudents = ManageStudentRecord::where('model_type', CourseAssignment::class)
                ->where('model_id', $assignmentId)
                ->exists();
            
            if ($hasAssignedStudents) {
                $validator->errors()->add('assignment', 'Cannot delete assignment. One or more students are assigned to this assignment.');
            }
            
            // Get course_id from AcdemicCourse
            $academicCourse = $assignment->acdemicCourses;
            if ($academicCourse) {
                $courseId = $academicCourse->course_id;
                
                // Check for parent purchases via CourseUser
                $hasPurchases = CourseUser::where('course_id', $courseId)
                    ->exists();
                
                if ($hasPurchases) {
                    $validator->errors()->add('assignment', 'Cannot delete assignment. One or more parents have purchased the course associated with this assignment.');
                }
                
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
                
                if ($hasActiveSubscriptions) {
                    $validator->errors()->add('assignment', 'Cannot delete assignment. One or more parents have active subscriptions for the course associated with this assignment.');
                }
            }
        });
    }
}

