<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Http\Controllers\Controller;
use App\Models\AcdemicCourse;
use App\Models\Announcement;
use App\Models\Award;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseAssignment;
use App\Models\CourseTest;
use App\Models\CourseTimeSlot;
use App\Models\ManageStudentRecord;
use App\Models\MockExam;
use App\Models\Mode;
use App\Models\Paper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TutorDashboardController extends Controller
{
    protected function tutorCourseIds(): array
    {
        return auth()->user()->tutorCourses()->pluck('id')->toArray();
    }

    protected function tutorAcademicCourseIds(): array
    {
        $courseIds = $this->tutorCourseIds();
        if (empty($courseIds)) {
            return [];
        }
        return AcdemicCourse::whereIn('course_id', $courseIds)->pluck('id')->toArray();
    }

    /**
     * Dashboard stats for the logged-in tutor.
     */
    public function dashboard()
    {
        $courseIds = $this->tutorCourseIds();
        $academicCourseIds = $this->tutorAcademicCourseIds();

        $coursesCount = count($courseIds);
        $studentsCount = ManageStudentRecord::whereIn('course_id', $courseIds)->distinct('buyer_id')->count('buyer_id');
        $assignmentsCount = CourseAssignment::whereIn('acdemic_course_id', $academicCourseIds)->count();
        $timeSlotsCount = CourseTimeSlot::whereIn('academic_course_id', $academicCourseIds)->count();

        $testsCount = 0;
        if (!empty($academicCourseIds)) {
            $testsCount = CourseTest::whereHas('courseTopic.courseAssignment', function ($q) use ($academicCourseIds) {
                $q->whereIn('acdemic_course_id', $academicCourseIds);
            })->count();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'courses_count' => $coursesCount,
                'students_count' => $studentsCount,
                'assignments_count' => $assignmentsCount,
                'timeslots_count' => $timeSlotsCount,
                'tests_count' => $testsCount,
            ],
        ]);
    }

    /**
     * All courses assigned to this tutor (from admin panel course assignment).
     * Uses query builder only so every row in course_tutor is returned with no Eloquent filtering.
     */
    public function courses()
    {
        $userId = auth()->id();

        // Get every course_id assigned to this tutor (direct from pivot, no model)
        $courseIds = DB::table('course_tutor')
            ->where('user_id', $userId)
            ->pluck('course_id')
            ->values()
            ->all();

        if (empty($courseIds)) {
            return response()->json(['success' => true, 'data' => []]);
        }

        // Load courses via query builder (no Eloquent model / scopes) so all assigned courses are returned
        $courses = DB::table('courses')
            ->whereIn('id', $courseIds)
            ->orderBy('name')
            ->get(['id', 'name', 'status', 'slug']);

        // Which course IDs have online mode?
        $onlineModeId = Mode::whereRaw('LOWER(name) = ?', ['online'])->value('id');
        $courseIdsWithOnlineMode = [];
        if ($onlineModeId) {
            $courseIdsWithOnlineMode = DB::table('mode_user')
                ->where('mode_id', $onlineModeId)
                ->whereIn('course_id', $courseIds)
                ->pluck('course_id')
                ->toArray();
        }

        $statusLabels = [
            1 => 'Pending',
            (int) config('constants.statuses.APPROVED', 2) => 'Active',
            (int) config('constants.statuses.REJECTED', 3) => 'Inactive',
        ];

        $data = [];
        foreach ($courses as $course) {
            $data[] = [
                'id' => $course->id,
                'name' => $course->name,
                'status' => $course->status,
                'status_label' => $statusLabels[$course->status] ?? 'Unknown',
                'slug' => $course->slug,
                'has_online_mode' => in_array($course->id, $courseIdsWithOnlineMode),
            ];
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Students grouped by course (only courses delivered by this tutor).
     */
    public function students(Request $request)
    {
        $courseIds = $this->tutorCourseIds();
        if (empty($courseIds)) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $perPage = $request->input('per_page', 15);
        $courseId = $request->input('course_id');

        $query = ManageStudentRecord::with(['course:id,name', 'buyer:id,first_name,last_name,email'])
            ->whereIn('course_id', $courseIds)
            ->whereNull('deleted_at');

        if ($courseId && in_array((int) $courseId, $courseIds)) {
            $query->where('course_id', $courseId);
        }

        $records = $query->latest()->paginate($perPage);

        return response()->json(['success' => true, 'data' => $records]);
    }

    /**
     * Time slots for tutor's academic courses.
     */
    public function timeSlots(Request $request)
    {
        $academicCourseIds = $this->tutorAcademicCourseIds();
        if (empty($academicCourseIds)) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $slots = CourseTimeSlot::with(['courses:id,name', 'locations:id,name', 'weekDays:id,name'])
            ->whereIn('academic_course_id', $academicCourseIds)
            ->whereNull('deleted_at')
            ->orderBy('start_time')
            ->get();

        return response()->json(['success' => true, 'data' => $slots]);
    }

    /**
     * Assignments for tutor's academic courses.
     */
    public function assignments(Request $request)
    {
        $academicCourseIds = $this->tutorAcademicCourseIds();
        if (empty($academicCourseIds)) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $assignments = CourseAssignment::with(['weeks:id,week_number,start_date,end_date'])
            ->whereIn('acdemic_course_id', $academicCourseIds)
            ->orderBy('id')
            ->get();

        return response()->json(['success' => true, 'data' => $assignments]);
    }

    /**
     * Tests for tutor's course content (read-only list).
     */
    public function tests(Request $request)
    {
        $academicCourseIds = $this->tutorAcademicCourseIds();
        if (empty($academicCourseIds)) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $tests = CourseTest::with(['courseTopic.courseAssignment.weeks:id,week_number', 'courseTopic.subject:id,name'])
            ->whereHas('courseTopic.courseAssignment', function ($q) use ($academicCourseIds) {
                $q->whereIn('acdemic_course_id', $academicCourseIds);
            })
            ->orderBy('id')
            ->get();

        return response()->json(['success' => true, 'data' => $tests]);
    }

    /**
     * Mock exams (read-only list for reference).
     */
    public function mockExams(Request $request)
    {
        $list = MockExam::where('status', config('constants.statuses.APPROVED'))
            ->select('id', 'name', 'slug', 'status')
            ->orderBy('name')
            ->paginate($request->input('per_page', 15));

        return response()->json(['success' => true, 'data' => $list]);
    }

    /**
     * Papers (read-only list for reference).
     */
    public function papers(Request $request)
    {
        $list = Paper::where('status', config('constants.statuses.APPROVED'))
            ->select('id', 'name', 'slug', 'status')
            ->orderBy('name')
            ->paginate($request->input('per_page', 15));

        return response()->json(['success' => true, 'data' => $list]);
    }

    /**
     * Announcements targeting Tutor role.
     */
    public function announcements(Request $request)
    {
        $query = Announcement::where('status', 1) // published
            ->whereHas('roles', function ($q) {
                $q->where('name', config('constants.roles.TUTOR'));
            })
            ->with(['module:id,name'])
            ->orderBy('created_at', 'desc');

        $list = $query->paginate($request->input('per_page', 15));
        return response()->json(['success' => true, 'data' => $list]);
    }

    /**
     * Awards (read-only list).
     */
    public function awards(Request $request)
    {
        $list = Award::orderBy('id')->paginate($request->input('per_page', 15));
        return response()->json(['success' => true, 'data' => $list]);
    }

    /**
     * Certificates for students in tutor's courses.
     */
    public function certificates(Request $request)
    {
        $courseIds = $this->tutorCourseIds();
        if (empty($courseIds)) {
            return response()->json(['success' => true, 'data' => []]);
        }
        $studentIds = ManageStudentRecord::whereIn('course_id', $courseIds)->distinct()->pluck('buyer_id')->toArray();
        if (empty($studentIds)) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $query = Certificate::with(['student:id,first_name,last_name,email', 'award:id,name'])
            ->whereIn('student_id', $studentIds)
            ->orderBy('created_at', 'desc');

        $list = $query->paginate($request->input('per_page', 15));
        return response()->json(['success' => true, 'data' => $list]);
    }
}
