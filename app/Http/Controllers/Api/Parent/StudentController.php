<?php

namespace App\Http\Controllers\Api\Parent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Parent\AddStudentRequest;
use App\Http\Requests\Api\Parent\StudentListRequest;
use App\Http\Requests\Api\Parent\UpdateStudentRequest;
use App\Models\Role;
use App\Models\StudentDetail;
use App\Models\User;
use App\Services\StudentService;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class StudentController extends Controller
{
    protected $studentService;

    public function __construct(StudentService $studentService)
    {
        $this->studentService = $studentService;
    }

    /**
     * Display a listing of students for the authenticated parent.
     */
    public function index(StudentListRequest $request)
    {
        try {
            $parentId = auth()->user()->id;
            $search = $request->input('search');
            $perPage = $request->input('per_page', 10);

            $query = StudentDetail::with([
                'student:id,first_name,last_name,email',
                'year:id,name',
                'month:id,name', 
                'day:id,name',
                'region:id,name',
                'gender:id,name',
                'targetSchool:id,name'
            ])->where('parent_id', $parentId);

            // Apply search filter if provided
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('student', function ($studentQuery) use ($search) {
                        $studentQuery->where('first_name', 'LIKE', "%{$search}%")
                                   ->orWhere('last_name', 'LIKE', "%{$search}%")
                                   ->orWhere('email', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('year', function ($yearQuery) use ($search) {
                        $yearQuery->where('name', 'LIKE', "%{$search}%");
                    })
                    ->orWhere('display_name', 'LIKE', "%{$search}%")
                    ->orWhere('bio', 'LIKE', "%{$search}%");
                });
            }

            $students = $query->orderBy('created_at', 'desc')
                             ->paginate($perPage);

            $response = [
                'success' => true,
                'message' => 'Students retrieved successfully.',
                'data' => $students
            ];

            return response()->json($response, 200);

        } catch (\Exception $e) {
            Log::error("Failed to retrieve students. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving students.',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    public function store(AddStudentRequest $request)
    {
        try {
            DB::beginTransaction();
            $userData = $request->only([
                'first_name',
                'last_name',
                'email',
            ]);
            $userData['password'] = Hash::make($request->input('password'));
            $user = User::create($userData);
            if ($user) {
                $user->roles()->attach(Role::where('name', config('constants.roles.STUDENT'))->first(), [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $studentData = $request->only([
                    'year_id',
                    'month_id',
                    'day_id',
                    'region_id',
                    'gender_id',
                    'target_school_id',
                    'display_name',
                    'show_answer_after_n_attempts',
                    'allow_view_examiner_report_for_mocks',
                    'can_change_password',
                    'bio',
                ]);
                
                $studentData['parent_id'] = auth()->user()->id; // Assuming the parent is authenticated
                $studentData['child_id'] = $user->id;
              
                $user = StudentDetail::create($studentData);
            
            }
            DB::commit();
            $response = [
                'success' => true,
                'message' => 'Student Created Successfully.',
            ];
            return response()->json($response, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to create student. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            return sendError('error', ['error' => 'An error occurred during create.'], 500);
        }
    }

    /**
     * Show the form for editing the specified student.
     */
    public function edit($studentId)
    {
        try {
            $parentId = auth()->user()->id;
            
            $student = $this->studentService->getStudentForEdit($studentId, $parentId);
            
            if (!$student) {
                return sendError('Student not found', ['error' => 'Student not found or you do not have permission to view this student.'], 404);
            }

            $response = [
                'success' => true,
                'message' => 'Student details retrieved successfully.',
                'data' => $student
            ];

            return response()->json($response, 200);

        } catch (\Exception $e) {
            Log::error("Failed to retrieve student for edit. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            return sendError('error', ['error' => 'An error occurred while retrieving student details.'], 500);
        }
    }

    /**
     * Update the specified student in storage.
     */
    public function update(UpdateStudentRequest $request, $studentId)
    {
        try {
            DB::beginTransaction();
            
            $parentId = auth()->user()->id;
            
            // Prepare user data (excluding password)
            $userData = $request->only([
                'first_name',
                'last_name',
                'email',
            ]);

            // Prepare student data
            $studentData = $request->only([
                'year_id',
                'month_id',
                'day_id',
                'region_id',
                'gender_id',
                'target_school_id',
                'display_name',
                'show_answer_after_n_attempts',
                'allow_view_examiner_report_for_mocks',
                'can_change_password',
                'bio',
            ]);

            $updatedStudent = $this->studentService->updateStudent($studentId, $parentId, $userData, $studentData);

            DB::commit();
            
            $response = [
                'success' => true,
                'message' => 'Student updated successfully.',
                'data' => $updatedStudent
            ];

            return response()->json($response, 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to update student. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            return sendError('error', ['error' => 'An error occurred during update.'], 500);
        }
    }
}
