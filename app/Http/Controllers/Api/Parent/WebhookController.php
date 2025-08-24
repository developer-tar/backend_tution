<?php

namespace App\Http\Controllers\Api\Parent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Parent\AddStudentRequest;
use App\Models\Role;
use App\Models\StudentDetail;
use App\Models\User;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class StudentController extends Controller
{
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
}
