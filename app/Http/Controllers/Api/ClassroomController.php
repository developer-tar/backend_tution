<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Course;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ClassroomController extends Controller
{
    /**
     * List classrooms for the logged-in tutor.
     */
    public function index(Request $request): JsonResponse
    {
        $statusOrder = [Classroom::STATUS_PENDING => 0, Classroom::STATUS_ONGOING => 1, Classroom::STATUS_ENDED => 2];

        $classrooms = Classroom::where('user_id', auth()->id())
            ->with(['course:id,name', 'tutor:id,first_name,last_name'])
            ->orderBy('start_time')
            ->get()
            ->map(function (Classroom $classroom) {
                return [
                    'id' => $classroom->id,
                    'name' => $classroom->name,
                    'room_code' => $classroom->room_code,
                    'drawing_enabled' => $classroom->drawing_enabled ?? true,
                    'description' => $classroom->description,
                    'schedule_summary' => $classroom->schedule_summary,
                    'start_time' => $classroom->start_time?->toIso8601String(),
                    'end_time' => $classroom->end_time?->toIso8601String(),
                    'status' => $classroom->status,
                    'course' => $classroom->course ? ['id' => $classroom->course->id, 'name' => $classroom->course->name] : null,
                    'tutor' => $classroom->tutor ? ['id' => $classroom->tutor->id, 'full_name' => $classroom->tutor->full_name] : null,
                ];
            })
            ->sortBy(function ($row) use ($statusOrder) {
                $order = $statusOrder[$row['status']] ?? 3;
                return [$order, $row['start_time'] ?? ''];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $classrooms,
        ]);
    }

    /**
     * Create a new classroom (tutor). Jitsi Meet room_code is auto-generated.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'schedule_summary' => ['nullable', 'string', 'max:255'],
            'start_time' => ['nullable', 'date'],
            'end_time' => ['nullable', 'date', 'after_or_equal:start_time'],
        ]);

        $user = auth()->user();
        $course = Course::findOrFail($validated['course_id']);

        if (!$user->tutorCourses()->where('courses.id', $course->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not assigned as tutor for this course.',
            ], 403);
        }

        $startTime = isset($validated['start_time']) ? $validated['start_time'] : now();
        $classroom = Classroom::create([
            'course_id' => $course->id,
            'user_id' => $user->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'capacity' => $validated['capacity'] ?? null,
            'schedule_summary' => $validated['schedule_summary'] ?? null,
            'start_time' => $startTime,
            'end_time' => $validated['end_time'] ?? null,
        ]);

        $classroom->load(['course:id,name', 'tutor:id,first_name,last_name']);

        return response()->json([
            'success' => true,
            'message' => 'Classroom created.',
            'classroom' => $classroom,
        ], 201);
    }

    /**
     * Get classroom by room_code for joining (Jitsi). Returns data for frontend to init Jitsi Meet.
     */
    public function join(string $roomCode): JsonResponse
    {
        $classroom = Classroom::where('room_code', $roomCode)
            ->with(['course:id,name', 'tutor:id,first_name,last_name'])
            ->first();

        if (!$classroom) {
            return response()->json([
                'success' => false,
                'message' => 'Classroom not found.',
            ], 404);
        }

        $user = auth()->user();
        $displayName = $user ? $user->full_name : 'Guest';

        return response()->json([
            'success' => true,
            'classroom' => $classroom,
            'jitsi' => [
                'domain' => 'meet.jit.si',
                'room_name' => $classroom->room_code,
                'display_name' => $displayName,
            ],
        ]);
    }

    /**
     * Start a meeting: set start_time to now if not already set. Called when tutor clicks Start on a scheduled class.
     */
    public function startMeeting(int $id): JsonResponse
    {
        $classroom = Classroom::where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$classroom) {
            return response()->json([
                'success' => false,
                'message' => 'Classroom not found or you are not the tutor.',
            ], 404);
        }

        if (!$classroom->start_time) {
            $classroom->start_time = now();
            $classroom->save();
        }

        $classroom->load(['course:id,name', 'tutor:id,first_name,last_name']);

        return response()->json([
            'success' => true,
            'message' => 'Meeting started.',
            'classroom' => $classroom,
        ]);
    }

    /**
     * End a meeting: set end_time to now. Tutor calls this when they leave the meeting.
     */
    public function endMeeting(int $id): JsonResponse
    {
        $classroom = Classroom::where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$classroom) {
            return response()->json([
                'success' => false,
                'message' => 'Classroom not found or you are not the tutor.',
            ], 404);
        }

        $classroom->end_time = now();
        $classroom->save();

        return response()->json([
            'success' => true,
            'message' => 'Meeting ended.',
            'classroom' => $classroom->fresh(['course:id,name', 'tutor:id,first_name,last_name']),
        ]);
    }

    /**
     * Show the Jitsi Meet live classroom page (web). Uses room_code in URL.
     */
    public function live(string $roomCode): View
    {
        $classroom = Classroom::where('room_code', $roomCode)
            ->with(['course:id,name', 'tutor:id,first_name,last_name'])
            ->first();

        if (!$classroom) {
            throw new NotFoundHttpException('Classroom not found.');
        }

        return view('classroom.live', compact('classroom'));
    }
}