<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Student\CAssignmentRequest;
use App\Models\CourseAssignment;
use App\Models\CourseSubTopic;
use App\Models\CourseTest;
use App\Models\CourseTopic;
use App\Models\ManageStudentRecord;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AssignmentController extends Controller {
    public function currentAssignment(CAssignmentRequest $request) {
        $userId = auth()->user()->id;
        $subjectId = $request->subject_id;
        $chooseTitle = $request->choose_title;



        $date = Carbon::parse('2025-01-04 10:00:00'); // or any input datetime

        
        $collectAssignmentIds = ManageStudentRecord::with([
            'course.acdemiccourse.assignment' => function ($q) use ($date) {
                $q->whereHas('weeks', function ($q) use ($date) {
                    $q->where('start_date', '<=', $date)
                        ->where('end_date', '>=', $date);
                });
            },
            'course.acdemiccourse.assignment.weeks' => function ($q) use ($date) {
                $q->where('start_date', '<=', $date)
                    ->where('end_date', '>=', $date);
            }
        ])
            ->whereHas('course.acdemiccourse.assignment.weeks', function ($q) use ($date) {
                $q->where('start_date', '<=', $date)
                    ->where('end_date', '>=', $date);
            })
            ->whereNull('parent_id')
            ->where('buyer_id', $userId)
            ->get()
            ->pluck('course')
            ->pluck('acdemiccourse')
            ->flatten()
            ->pluck('assignment')
            ->flatten()
            ->pluck('id')
            ->unique()
            ->values(); //get the data for getting the assignment id
           
        $ids = ManageStudentRecord::with('children')->whereHas('children', function ($q) use ($chooseTitle) {
            $q->where('model_type', config('constants.assignment_content.' . $chooseTitle));
        })->where('model_type', CourseAssignment::class)->whereIn('model_id', $collectAssignmentIds)->get();
    }
}
// 2024-12-30 00:00:00
// 2025-01-05 22:00:00