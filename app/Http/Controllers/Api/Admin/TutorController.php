<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TutorController extends Controller
{
    /**
     * List all users with Tutor role (for admin panel).
     */
    public function index(Request $request)
    {
        try {
            $search = $request->input('search');
            $perPage = (int) $request->input('per_page', 10);

            $tutors = User::whereHas('roles', function ($q) {
                $q->where('name', config('constants.roles.TUTOR'));
            })
                ->withCount('tutorCourses')
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('first_name', 'LIKE', "%{$search}%")
                            ->orWhere('last_name', 'LIKE', "%{$search}%")
                            ->orWhere('email', 'LIKE', "%{$search}%")
                            ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', "%{$search}%");
                    });
                })
                ->orderBy('first_name')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Tutors fetched successfully.',
                'data' => $tutors,
            ], 200);
        } catch (\Exception $e) {
            Log::error("TutorController@index: {$e->getMessage()}");
            return sendError('error', ['error' => 'An error occurred while fetching tutors.'], 500);
        }
    }

    /**
     * Get a single tutor with assigned course ids (for assign-courses form).
     */
    public function show($id)
    {
        try {
            $tutor = User::whereHas('roles', function ($q) {
                $q->where('name', config('constants.roles.TUTOR'));
            })->find($id);

            if (!$tutor) {
                return sendError('error', ['error' => 'Tutor not found.'], 404);
            }

            $courseIds = $tutor->tutorCourses()->pluck('id')->toArray();

            return response()->json([
                'success' => true,
                'message' => 'Tutor fetched successfully.',
                'data' => [
                    'id' => $tutor->id,
                    'first_name' => $tutor->first_name,
                    'last_name' => $tutor->last_name,
                    'email' => $tutor->email,
                    'course_ids' => $courseIds,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error("TutorController@show: {$e->getMessage()}");
            return sendError('error', ['error' => 'An error occurred while fetching tutor.'], 500);
        }
    }

    /**
     * List courses for the course-assignment dropdown/checkboxes (id, name).
     */
    public function coursesForAssignment(Request $request)
    {
        try {
            $courses = Course::select('id', 'name', 'status', 'slug')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Courses fetched successfully.',
                'data' => $courses,
            ], 200);
        } catch (\Exception $e) {
            Log::error("TutorController@coursesForAssignment: {$e->getMessage()}");
            return sendError('error', ['error' => 'An error occurred while fetching courses.'], 500);
        }
    }

    /**
     * Sync courses assigned to a tutor (replace existing assignments).
     */
    public function updateCourses(Request $request, $id)
    {
        try {
            $tutor = User::whereHas('roles', function ($q) {
                $q->where('name', config('constants.roles.TUTOR'));
            })->find($id);

            if (!$tutor) {
                return sendError('error', ['error' => 'Tutor not found.'], 404);
            }

            $request->validate([
                'course_ids' => 'nullable|array',
                'course_ids.*' => 'integer|exists:courses,id',
            ]);

            $courseIds = $request->input('course_ids', []);

            $tutor->tutorCourses()->sync($courseIds);

            $updated = $tutor->tutorCourses()->pluck('id')->toArray();

            return response()->json([
                'success' => true,
                'message' => 'Courses assigned successfully.',
                'data' => ['course_ids' => $updated],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            Log::error("TutorController@updateCourses: {$e->getMessage()}");
            return sendError('error', ['error' => 'An error occurred while assigning courses.'], 500);
        }
    }
}
