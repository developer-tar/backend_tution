<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\UpdateStudentRequest;
use App\Http\Requests\Api\Admin\DeleteStudentRequest;
use App\Models\User;
use App\Models\StudentDetail;
use App\Models\ManageStudentRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class StudentController extends Controller
{
    /**
     * Display a listing of students.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(\Illuminate\Http\Request $request)
    {
        try {
            $search = $request->input('search');
            $perPage = $request->input('per_page', 10);

            // Fetch students (users with ROLE_STUDENT)
            $query = User::whereHas('roles', function ($q) {
                $q->where('name', config('constants.roles.STUDENT'));
            })
            // Join with student_details to get display_name etc.
            ->join('student_details', 'users.id', '=', 'student_details.child_id')
            ->select('users.*', 'student_details.id as student_detail_id', 'student_details.display_name', 'student_details.parent_id', 'student_details.year_id')
            ->with([
                'students' => function ($q) { // This relates to Parent->Students, might not be right for User(Student)
                    // User model: public function students() { return $this->hasMany(StudentDetail::class, 'parent_id', 'id'); }
                    // But here we are fetching STUDENTS. User model doesn't have 'studentDetail' relation pointing to itself?
                    // Actually User has `studentDetail` via `child_id`? No, User is the child.
                    // StudentDetail has `child_id` -> User.
                }
            ]);
            
            // Better approach: Query StudentDetail directly as it holds the main "Student" entity info
            $query = StudentDetail::with([
                'student:id,first_name,last_name,email',
                'parent:id,first_name,last_name,email', // Assuming parent relationship exists
                'year:id,name',
            ]);

            // Search functionality
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('student', function ($studentQuery) use ($search) {
                        $studentQuery->where('first_name', 'LIKE', "%{$search}%")
                                   ->orWhere('last_name', 'LIKE', "%{$search}%")
                                   ->orWhere('email', 'LIKE', "%{$search}%")
                                   ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', "%{$search}%");
                    })
                    ->orWhere('display_name', 'LIKE', "%{$search}%")
                    ->orWhereHas('year', function ($yearQuery) use ($search) {
                        $yearQuery->where('name', 'LIKE', "%{$search}%");
                    });
                });
            }

            $students = $query->latest()->paginate($perPage);

            // Append assigned courses manually or via relationship
            $students->getCollection()->transform(function ($student) {
                $studentUserId = $student->child_id;
                
                // Get assigned courses
                $assignedCourses = ManageStudentRecord::with('course:id,name')
                    ->where('buyer_id', $studentUserId)
                    ->whereNull('parent_id') // Typically admin assignments? Or all assignments? 
                    // Requirement says "Get All Students With Their Courses". Usually means all active courses.
                    ->get()
                    ->map(function ($record) {
                        return [
                            'id' => $record->id,
                            'buyer_id' => $record->buyer_id,
                            'course_id' => $record->course_id,
                            'is_completed' => $record->is_completed,
                            'created_at' => $record->created_at,
                            'course' => $record->course ? [
                                'id' => $record->course->id,
                                'name' => $record->course->name
                            ] : null
                        ];
                    });

                return [
                    'id' => $student->id,
                    'parent_id' => $student->parent_id,
                    'child_id' => $student->child_id,
                    'year_id' => $student->year_id,
                    'display_name' => $student->display_name,
                    'student' => $student->student,
                    'parent' => $student->parent,
                    'year' => $student->year,
                    'assigned_courses_count' => $assignedCourses->count(),
                    'assigned_courses' => $assignedCourses
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Students retrieved successfully with assigned courses.',
                'data' => $students
            ], 200);

        } catch (\Exception $e) {
            Log::error("Failed to fetch students. Message => {$e->getMessage()}");
            return sendError('error', ['error' => 'An error occurred while fetching students.'], 500);
        }
    }

    /**
     * Display the specified student with all details and assigned courses.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            // Find student by StudentDetail ID or User ID
            $studentDetail = $this->findStudentById($id);
            
            if (!$studentDetail) {
                return sendError('error', ['error' => 'Student not found.'], 404);
            }

            // Load student with all relationships
            $studentData = $this->getStudentWithDetails($studentDetail->id);

            return response()->json([
                'success' => true,
                'message' => 'Student details fetched successfully.',
                'data' => $studentData,
            ], 200);
        } catch (\Exception $e) {
            Log::error("Failed to fetch student. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            return sendError('error', ['error' => 'An error occurred while fetching student.'], 500);
        }
    }

    /**
     * Update the specified student.
     *
     * @param  UpdateStudentRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateStudentRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            // Find student by StudentDetail ID or User ID
            $studentDetail = $this->findStudentById($id);
            
            if (!$studentDetail) {
                DB::rollBack();
                return sendError('error', ['error' => 'Student not found.'], 404);
            }

            $studentUser = User::find($studentDetail->child_id);

            if (!$studentUser) {
                DB::rollBack();
                return sendError('error', ['error' => 'Student user record not found.'], 404);
            }

            // Update User fields
            if ($request->has('first_name')) {
                $studentUser->first_name = $request->first_name;
            }
            if ($request->has('last_name')) {
                $studentUser->last_name = $request->last_name;
            }
            if ($request->has('email')) {
                $studentUser->email = $request->email;
            }
            if ($request->has('password')) {
                $studentUser->password = Hash::make($request->password);
            }
            $studentUser->save();

            // Update StudentDetail fields
            if ($request->has('year_id')) {
                $studentDetail->year_id = $request->year_id;
            }
            if ($request->has('month_id')) {
                $studentDetail->month_id = $request->month_id;
            }
            if ($request->has('day_id')) {
                $studentDetail->day_id = $request->day_id;
            }
            if ($request->has('region_id')) {
                $studentDetail->region_id = $request->region_id;
            }
            if ($request->has('gender_id')) {
                $studentDetail->gender_id = $request->gender_id;
            }
            if ($request->has('target_school_id')) {
                $studentDetail->target_school_id = $request->target_school_id;
            }
            if ($request->has('display_name')) {
                $studentDetail->display_name = $request->display_name;
            }
            if ($request->has('show_answer_after_n_attempts')) {
                $studentDetail->show_answer_after_n_attempts = $request->show_answer_after_n_attempts;
            }
            if ($request->has('allow_view_examiner_report_for_mocks')) {
                $studentDetail->allow_view_examiner_report_for_mocks = $request->allow_view_examiner_report_for_mocks;
            }
            if ($request->has('can_change_password')) {
                $studentDetail->can_change_password = $request->can_change_password;
            }
            if ($request->has('bio')) {
                $studentDetail->bio = $request->bio;
            }
            $studentDetail->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Student updated successfully.',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to update student. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            return sendError('error', ['error' => 'An error occurred during update.'], 500);
        }
    }

    /**
     * Remove the specified student and all assigned courses.
     *
     * @param  DeleteStudentRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(DeleteStudentRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            // Find student by StudentDetail ID or User ID
            $studentDetail = $this->findStudentById($id);
            
            if (!$studentDetail) {
                DB::rollBack();
                return sendError('error', ['error' => 'Student not found.'], 404);
            }

            $studentUserId = $studentDetail->child_id;

            // Check if student has any assigned courses
            $hasAssignments = ManageStudentRecord::where('buyer_id', $studentUserId)->exists();

            if ($hasAssignments) {
                DB::rollBack();
                return sendError('error', ['error' => 'Student cannot be deleted because they are assigned to a course.'], 400);
            }

            // Delete StudentDetail record (cascade will handle User deletion)
            $studentDetail->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Student and all assigned courses deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to delete student. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            return sendError('error', ['error' => 'An error occurred during deletion.'], 500);
        }
    }

    /**
     * Find student by StudentDetail ID or User ID.
     *
     * @param  int  $id
     * @return StudentDetail|null
     */
    private function findStudentById($id)
    {
        // First try to find by StudentDetail ID
        $studentDetail = StudentDetail::find($id);
        
        if ($studentDetail) {
            return $studentDetail;
        }

        // If not found, try to find by User ID (child_id)
        $studentDetail = StudentDetail::where('child_id', $id)->first();
        
        return $studentDetail;
    }

    /**
     * Get student with all details and assigned courses.
     *
     * @param  int  $studentDetailId
     * @return array
     */
    private function getStudentWithDetails($studentDetailId)
    {
        $studentDetail = StudentDetail::with([
            'student:id,first_name,last_name,email',
            'parent:id,first_name,last_name,email',
            'year:id,name',
            'month:id,name',
            'day:id,name',
            'region:id,name',
            'gender:id,name',
            'targetSchool:id,name',
        ])->find($studentDetailId);

        if (!$studentDetail) {
            return null;
        }

        $studentUserId = $studentDetail->child_id;

        // Get all assigned courses for this student
        $assignedCourses = ManageStudentRecord::with('course:id,name')
            ->where('buyer_id', $studentUserId)
            ->get()
            ->map(function ($record) {
                return [
                    'id' => $record->id,
                    'course_id' => $record->course_id,
                    'course_name' => $record->course->name ?? null,
                    'status' => $record->status,
                    'is_completed' => $record->is_completed,
                    'assigned_at' => $record->created_at ? $record->created_at->format('Y-m-d H:i:s') : null,
                ];
            });

        return [
            'student_detail_id' => $studentDetail->id,
            'student_user' => [
                'id' => $studentDetail->student->id ?? null,
                'first_name' => $studentDetail->student->first_name ?? null,
                'last_name' => $studentDetail->student->last_name ?? null,
                'email' => $studentDetail->student->email ?? null,
            ],
            'parent' => [
                'id' => $studentDetail->parent->id ?? null,
                'first_name' => $studentDetail->parent->first_name ?? null,
                'last_name' => $studentDetail->parent->last_name ?? null,
                'email' => $studentDetail->parent->email ?? null,
            ],
            'display_name' => $studentDetail->display_name,
            'year' => $studentDetail->year ? ['id' => $studentDetail->year->id, 'name' => $studentDetail->year->name] : null,
            'month' => $studentDetail->month ? ['id' => $studentDetail->month->id, 'name' => $studentDetail->month->name] : null,
            'day' => $studentDetail->day ? ['id' => $studentDetail->day->id, 'name' => $studentDetail->day->name] : null,
            'region' => $studentDetail->region ? ['id' => $studentDetail->region->id, 'name' => $studentDetail->region->name] : null,
            'gender' => $studentDetail->gender ? ['id' => $studentDetail->gender->id, 'name' => $studentDetail->gender->name] : null,
            'target_school' => $studentDetail->targetSchool ? ['id' => $studentDetail->targetSchool->id, 'name' => $studentDetail->targetSchool->name] : null,
            'show_answer_after_n_attempts' => $studentDetail->show_answer_after_n_attempts,
            'allow_view_examiner_report_for_mocks' => $studentDetail->allow_view_examiner_report_for_mocks,
            'can_change_password' => $studentDetail->can_change_password,
            'bio' => $studentDetail->bio,
            'assigned_courses' => $assignedCourses,
        ];
    }
}



