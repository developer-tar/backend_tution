<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\UpdateParentRequest;
use App\Http\Requests\Api\Admin\DeleteParentRequest;
use App\Models\User;
use App\Models\StudentDetail;
use App\Models\ManageStudentRecord;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class ParentController extends Controller
{
    /**
     * Display a listing of the parents.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(\Illuminate\Http\Request $request)
    {
        try {
            $search = $request->input('search');
            $perPage = $request->input('per_page', 10);

            $parents = User::whereHas('roles', function ($q) {
                $q->where('name', config('constants.roles.PARENT'));
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'LIKE', "%{$search}%")
                      ->orWhere('last_name', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%")
                      ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', "%{$search}%");
                });
            })
            ->withCount('students') // Assuming 'students' relationship exists on User or we use StudentDetail
            ->latest()
            ->paginate($perPage);

            // Add student count manually if relationship doesn't exist on User model directly
            // Check User model for 'students' relationship
            
            return response()->json([
                'success' => true,
                'message' => 'Parents fetched successfully.',
                'data' => $parents,
            ], 200);
        } catch (\Exception $e) {
            Log::error("Failed to fetch parents. Message => {$e->getMessage()}");
            return sendError('error', ['error' => 'An error occurred while fetching parents.'], 500);
        }
    }

    /**
     * Display the specified parent with all students and their courses.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            // Verify parent exists and has Parent role
            $parent = User::with('roles')->find($id);
            
            if (!$parent) {
                return sendError('error', ['error' => 'Parent not found.'], 404);
            }

            // Check if user has Parent role
            $hasParentRole = $parent->roles()->where('name', config('constants.roles.PARENT'))->exists();
            
            if (!$hasParentRole) {
                return sendError('error', ['error' => 'User does not have Parent role.'], 422);
            }

            // Check if parent has students
            $hasStudents = StudentDetail::where('parent_id', $id)->exists();
            
            if (!$hasStudents) {
                return sendError('error', ['error' => 'Parent does not have any students.'], 422);
            }

            // Load parent with all students and their courses
            $parentData = $this->getParentWithStudents($id);

            return response()->json([
                'success' => true,
                'message' => 'Parent details fetched successfully.',
                'data' => $parentData,
            ], 200);
        } catch (\Exception $e) {
            Log::error("Failed to fetch parent. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            return sendError('error', ['error' => 'An error occurred while fetching parent.'], 500);
        }
    }

    /**
     * Update the specified parent.
     *
     * @param  UpdateParentRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateParentRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            $parent = User::findOrFail($id);

            // Update User fields
            if ($request->has('first_name')) {
                $parent->first_name = $request->first_name;
            }
            if ($request->has('last_name')) {
                $parent->last_name = $request->last_name;
            }
            if ($request->has('email')) {
                $parent->email = $request->email;
            }
            if ($request->has('password')) {
                $parent->password = Hash::make($request->password);
            }

            $parent->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Parent updated successfully.',
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return sendError('error', ['error' => 'Parent not found.'], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to update parent. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            return sendError('error', ['error' => 'An error occurred during update.'], 500);
        }
    }

    /**
     * Remove the specified parent and all associated students.
     *
     * @param  DeleteParentRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(DeleteParentRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            $parent = User::findOrFail($id);

            // Get all StudentDetail records for this parent
            $studentDetails = StudentDetail::where('parent_id', $id)->get();

            // For each student, delete their assigned courses (ManageStudentRecord)
            foreach ($studentDetails as $studentDetail) {
                $studentUserId = $studentDetail->child_id;
                
                // Delete all ManageStudentRecord records for this student
                ManageStudentRecord::where('buyer_id', $studentUserId)->delete();
            }

            // Delete all StudentDetail records (cascade will handle User deletion)
            StudentDetail::where('parent_id', $id)->delete();

            // Delete parent User record
            $parent->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Parent and all associated students deleted successfully.',
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return sendError('error', ['error' => 'Parent not found.'], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to delete parent. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            return sendError('error', ['error' => 'An error occurred during deletion.'], 500);
        }
    }

    /**
     * Get parent subscriptions.
     * 
     * @param int $parentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getParentSubscriptions($parentId)
    {
        try {
            $parent = User::find($parentId);
            
            if (!$parent) {
                return sendError('error', ['error' => 'Parent not found.'], 404);
            }

            $service = new SubscriptionService();
            $subscriptions = $service->getSubscriptionsForParent($parentId);

            return response()->json([
                'success' => true,
                'data' => $subscriptions,
                'message' => 'Parent subscriptions fetched successfully.',
            ], 200);

        } catch (\Exception $e) {
            Log::error("Failed to fetch parent subscriptions. Message => {$e->getMessage()}");
            return sendError('error', ['error' => 'An error occurred while fetching subscriptions.'], 500);
        }
    }

    /**
     * Get parent with all students and their courses.
     *
     * @param  int  $parentId
     * @return array
     */
    private function getParentWithStudents($parentId)
    {
        $parent = User::find($parentId);

        $students = StudentDetail::with([
            'student:id,first_name,last_name,email',
            'year:id,name',
            'month:id,name',
            'day:id,name',
            'region:id,name',
            'gender:id,name',
            'targetSchool:id,name',
        ])
        ->where('parent_id', $parentId)
        ->get();

        $studentsData = $students->map(function ($studentDetail) {
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
                        // 'status' => $record->status, // Column does not exist
                        'is_completed' => $record->is_completed,
                        'assigned_at' => $record->created_at ? $record->created_at->format('Y-m-d H:i:s') : null,
                    ];
                });

            return [
                'student_detail_id' => $studentDetail->id,
                'student_user_id' => $studentDetail->child_id,
                'first_name' => $studentDetail->student->first_name ?? null,
                'last_name' => $studentDetail->student->last_name ?? null,
                'email' => $studentDetail->student->email ?? null,
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
        });

        return [
            'id' => $parent->id,
            'first_name' => $parent->first_name,
            'last_name' => $parent->last_name,
            'email' => $parent->email,
            'full_name' => $parent->full_name,
            'students' => $studentsData,
        ];
    }
}



