<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AdminLoginRequest;
use App\Http\Requests\Api\ChangePasswordRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\Cart;
use App\Models\Course;
use App\Models\AcdemicCourse;
use App\Models\CourseAssignment;
use App\Models\Role;
use App\Models\User;
use App\Notifications\EmailVerificationNotification;
use App\Notifications\EnrollmentConfirmationNotification;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** Valid Passport scope names (must match Passport::tokensCan() keys) */
    private const VALID_SCOPES = ['Admin', 'Student', 'Parent', 'Tutor', 'School'];

    /**
     * Map role name to a valid Passport scope to avoid "Invalid scope(s) provided".
     */
    private function scopeForRole(?string $roleName, string $default = 'Student'): string
    {
        if (!$roleName) {
            return $default;
        }
        $normalized = strtolower(trim($roleName));
        $map = [
            'admin' => 'Admin',
            'student' => 'Student',
            'parent' => 'Parent',
            'tutor' => 'Tutor',
            'school' => 'School',
        ];
        return $map[$normalized] ?? $default;
    }

    /**
     * User login API method
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function AdminLogin(AdminLoginRequest $request)
    {
        try {
            $credentials = $request->only('email', 'password');
            if (Auth::attempt($credentials)) {
                $user = Auth::user();
                $role = $user->roles()->first();
                if (!$role) {
                    errorLog("Role has not found for user ID: {$user->id}");
                    return sendError('Not Found', ['error' => 'User role not found.'], 404);
                }
                if ($role?->name != config('constants.roles.ADMIN')) {
                    return sendError('Unauthorized', ['error' => 'You are not authorized to access admin panel.'], 403);
                }
                if ($user->status == config('constants.statuses.APPROVED')) {
                    $scopeName = $this->scopeForRole($role?->name, 'Admin');
                    $tokenResult = $user->createToken('accessToken', [$scopeName]);

                    $user = [
                        'id' => $user->id,
                        'full_name' => $user->full_name,
                        'email' => $user->email,
                        'role' => $role?->name,
                        'access_token' => $tokenResult->accessToken,
                    ];
                    $response = [
                        'success' => true,
                        'data' => $user,
                        'message' => 'You are successfully logged in.',
                    ];
                    return Response::json($response, 200);
                } else {
                    return sendError('Error', ['error' => 'This user is not active yet.'], 400);
                }
            } else {
                return sendError('Unauthorized', ['error' => 'Invalid email or password.'], 401);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            errorLog("Validation error in admin login: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'data' => ['error' => $e->getMessage()],
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return errorLog("Error occurred in admin login: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }
    public function login(LoginRequest $request)
    {

        return $this->authenticate($request);
    }
    /**
     * User registration API method
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(RegisterRequest $request)
    {
        try {

            DB::beginTransaction();
            $data = $request->all();
            $data['password'] = Hash::make($data['password']);
            $user = User::create($data);
            if ($user) {
                $role = $request->choose_the_role;
                $user->roles()->attach($role, ['created_at' => now(), 'updated_at' => now()]);
                $success['email'] = $user->email;
                $success['full_name'] = $user->full_name;
                $success['role'] = $user->roles()->first()?->name;
            }
            DB::commit();

            return sendResponse($success, 'User has been successfully created,please login', 201);
        } catch (Exception $e) {
            DB::rollBack();
            return errorLog("Failed to register user: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Combined registration for parent and student
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function registerParentAndStudent(Request $request)
    {
        try {
            // Validate request
            $validationRules = [
                // Parent fields
                'parent_first_name' => 'required|string',
                'parent_last_name' => 'required|string',
                'parent_email' => 'required|email|unique:users,email',
                // Student fields
                'student_first_name' => 'required|string',
                'student_last_name' => 'required|string',
            ];

            // Add optional password and email validations only if provided
            if ($request->has('parent_password') && !empty($request->parent_password)) {
                $validationRules['parent_password'] = 'string|min:8|max:10';
            }
            if ($request->has('student_email') && !empty($request->student_email)) {
                $validationRules['student_email'] = 'email|unique:users,email';
            }
            if ($request->has('student_password') && !empty($request->student_password)) {
                $validationRules['student_password'] = 'string|min:8|max:10';
            }

            $request->validate($validationRules);

            DB::beginTransaction();

            // Get role IDs
            $parentRole = Role::where('name', config('constants.roles.PARENT'))->first();
            $studentRole = Role::where('name', config('constants.roles.STUDENT'))->first();

            if (!$parentRole || !$studentRole) {
                return sendError('Error', ['error' => 'Parent or Student role not found.'], 404);
            }

            // Generate parent password if not provided
            $parentPassword = $request->parent_password;
            if (empty($parentPassword)) {
                // Generate a unique random password (10 characters)
                $parentPassword = Str::random(10);
            }

            // Create parent user
            $parentData = [
                'first_name' => $request->parent_first_name,
                'last_name' => $request->parent_last_name,
                'email' => $request->parent_email,
                'password' => Hash::make($parentPassword),
            ];
            $parent = User::create($parentData);
            $parent->roles()->attach($parentRole->id, ['created_at' => now(), 'updated_at' => now()]);

            // Generate student email if not provided
            $studentEmail = $request->student_email;
            if (empty($studentEmail)) {
                // Generate student email based on parent email
                $emailParts = explode('@', $request->parent_email);
                $studentEmail = $emailParts[0] . '+student@' . $emailParts[1];

                // Ensure uniqueness by adding a number if needed
                $counter = 1;
                while (User::where('email', $studentEmail)->exists()) {
                    $studentEmail = $emailParts[0] . '+student' . $counter . '@' . $emailParts[1];
                    $counter++;
                }
            }

            // Generate student password if not provided
            $studentPassword = $request->student_password;
            if (empty($studentPassword)) {
                // Generate a unique random password (10 characters)
                $studentPassword = Str::random(10);
            }

            // Generate unique username for student
            $baseUsername = strtolower($request->student_first_name . $request->student_last_name);
            $baseUsername = preg_replace('/[^a-z0-9]/', '', $baseUsername); // Remove special characters
            $studentUsername = $baseUsername;
            $counter = 1;
            while (User::where('username', $studentUsername)->exists()) {
                $studentUsername = $baseUsername . $counter;
                $counter++;
            }

            // Create student user
            $studentData = [
                'first_name' => $request->student_first_name,
                'last_name' => $request->student_last_name,
                'email' => $studentEmail,
                'username' => $studentUsername,
                'password' => Hash::make($studentPassword),
            ];
            $student = User::create($studentData);
            $student->roles()->attach($studentRole->id, ['created_at' => now(), 'updated_at' => now()]);

            DB::commit();

            // Send enrollment confirmation email to parent with student credentials
            try {
                $courseName = $request->course_name ?? null;
                $courseDates = $this->getCourseDates($courseName);
                $parent->notify(new EnrollmentConfirmationNotification(
                    $studentUsername,
                    $studentPassword,
                    $student->full_name,
                    $courseName,
                    $courseDates['start_date'] ?? null,
                    $courseDates['end_date'] ?? null
                ));
            } catch (Exception $e) {
                Log::error("Failed to send enrollment confirmation email to parent: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
            }

            // Send verification emails to both parent and student
            try {
                $parent->notify(new EmailVerificationNotification());
            } catch (Exception $e) {
                Log::error("Failed to send verification email to parent: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
            }

            try {
                $student->notify(new EmailVerificationNotification());
            } catch (Exception $e) {
                Log::error("Failed to send verification email to student: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
            }

            // Create access token for parent to proceed with payment immediately
            $parentRole = $parent->roles()->first();
            $scopeName = $this->scopeForRole($parentRole?->name, 'Parent');
            $parentToken = $parent->createToken('accessToken', [$scopeName])->accessToken;

            $success = [
                'parent' => [
                    'email' => $parent->email,
                    'full_name' => $parent->full_name,
                ],
                'student' => [
                    'email' => $student->email,
                    'full_name' => $student->full_name,
                ],
                'access_token' => $parentToken,
                'role' => $parentRole?->name,
            ];

            return sendResponse($success, 'Parent and Student have been successfully registered. Verification email has been sent.', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            DB::rollBack();
            return errorLog("Failed to register parent and student: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    public function authenticate(Request $request)
    {
        try {
            $credentials = $request->only('email', 'password');
            $oldSessionId = session()->getId(); // guest session

            Log::info('Login attempt started', [
                'old_session_id' => $oldSessionId,
                'email' => $request->email
            ]);

            if (Auth::attempt($credentials)) {
                $user = Auth::user();
                $role = $user->roles()->first();
                if (!$role) {
                    errorLog("Role has not found for user ID: {$user->id}");
                    return sendError('This user is not belong any role', ['error' => 'Something went Wrong'], 500);
                }


                if ($role && $request->filled('choose_the_role') && $role?->id != $request->choose_the_role) {
                    return sendError('Unauthorised', ['error' => "Credentails and user role has doesn't match"], 401);
                }
                $status = $user->status;
                if ($status == config('constants.statuses.APPROVED')) {
                    // Check if session ID changed during login
                    $currentSessionId = session()->getId();
                    if ($currentSessionId !== $oldSessionId) {
                        Log::warning('Session ID changed during login', [
                            'old_session_id' => $oldSessionId,
                            'new_session_id' => $currentSessionId,
                            'user_id' => $user->id
                        ]);
                    }

                    // Merge guest cart items to user cart before creating token
                    $this->_mergeGuestCart($oldSessionId);

                    $scopeName = $this->scopeForRole($role?->name, 'Student');
                    $user = [
                        'id' => $user->id,
                        'full_name' => $user->full_name,
                        'email' => $user->email,
                        'role' => $role?->name,
                        'access_token' => $user->createToken('accessToken', [$scopeName])->accessToken,
                    ];
                    $response = [
                        'success' => true,
                        'data' => $user,
                        'message' => 'You are successfully logged in.',
                    ];
                    return Response::json($response, 200);
                } else {
                    return sendError('Error', ['error' => 'This user is not active yet.'], 400);
                }
            } else {
                return sendError('Unauthorized', ['error' => 'Unauthorised'], 401);
            }
        } catch (Exception $e) {
            return errorLog("Error occurred in login: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Verify user email address
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyEmail(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|exists:users,id',
                'hash' => 'required|string',
                'expires' => 'required|string',
                'signature' => 'required|string',
            ]);

            $user = User::findOrFail($request->id);

            // Verify the signature using Laravel's URL signature validation
            $expectedUrl = url()->temporarySignedRoute(
                'verification.verify',
                now()->addHours(24),
                [
                    'id' => $user->getKey(),
                    'hash' => sha1($user->getEmailForVerification()),
                ]
            );

            // Reconstruct the request URL for validation
            $requestUrl = url('/api/email/verify') . '?' . http_build_query([
                'id' => $request->id,
                'hash' => $request->hash,
                'expires' => $request->expires,
                'signature' => $request->signature,
            ]);

            // Validate the signed URL
            if (!URL::hasValidSignature($request)) {
                return sendError('Invalid verification link', ['error' => 'The verification link is invalid or has expired.'], 400);
            }

            // Verify hash matches
            if ($request->hash !== sha1($user->getEmailForVerification())) {
                return sendError('Invalid verification link', ['error' => 'The verification link is invalid.'], 400);
            }

            // Check if already verified
            if ($user->hasVerifiedEmail()) {
                return sendResponse(['verified' => true], 'Email address has already been verified.', 200);
            }

            // Mark email as verified
            $user->markEmailAsVerified();

            return sendResponse(['verified' => true], 'Email address has been successfully verified.', 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return sendError('Validation failed', ['errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return errorLog("Failed to verify email: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Resend verification email
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resendVerificationEmail(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:users,email',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return sendError('User not found', ['error' => 'No user found with this email address.'], 404);
            }

            // Check if already verified
            if ($user->hasVerifiedEmail()) {
                return sendResponse(['verified' => true], 'Email address has already been verified.', 200);
            }

            // Send verification email
            try {
                $user->notify(new EmailVerificationNotification());
                return sendResponse(['sent' => true], 'Verification email has been sent successfully.', 200);
            } catch (Exception $e) {
                Log::error("Failed to send verification email: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
                return sendError('Failed to send verification email', ['error' => 'Please try again later.'], 500);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return errorLog("Failed to verify email: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Change password for authenticated user (parent or student)
     *
     * @param ChangePasswordRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changePassword(ChangePasswordRequest $request)
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return sendError('Unauthorized', ['error' => 'User not authenticated.'], 401);
            }

            // Update password
            $user->password = Hash::make($request->new_password);
            $user->save();

            return sendResponse([], 'Password changed successfully.', 200);
        } catch (Exception $e) {
            return errorLog("Failed to change password: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Get course start and end dates from database
     *
     * @param string|null $courseName
     * @return array
     */
    private function getCourseDates($courseName = null)
    {
        try {
            if (!$courseName) {
                return ['start_date' => null, 'end_date' => null];
            }

            // Find course by name
            $course = Course::where('name', $courseName)
                ->orWhere('slug', $courseName)
                ->first();

            if (!$course) {
                return ['start_date' => null, 'end_date' => null];
            }

            // Get all academic courses for this course
            $academicCourseIds = AcdemicCourse::where('course_id', $course->id)
                ->pluck('id')
                ->toArray();

            if (empty($academicCourseIds)) {
                return ['start_date' => null, 'end_date' => null];
            }

            // Get all course assignments for these academic courses
            $assignments = CourseAssignment::whereIn('acdemic_course_id', $academicCourseIds)
                ->with('weeks:id,start_date,end_date')
                ->get();

            if ($assignments->isEmpty()) {
                return ['start_date' => null, 'end_date' => null];
            }

            // Find earliest start_date and latest end_date from all weeks
            $startDates = [];
            $endDates = [];

            foreach ($assignments as $assignment) {
                if ($assignment->weeks) {
                    if ($assignment->weeks->start_date) {
                        $startDates[] = $assignment->weeks->start_date;
                    }
                    if ($assignment->weeks->end_date) {
                        $endDates[] = $assignment->weeks->end_date;
                    }
                }
            }

            $startDate = !empty($startDates) ? min($startDates) : null;
            $endDate = !empty($endDates) ? max($endDates) : null;

            return [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ];
        } catch (Exception $e) {
            Log::error("Failed to get course dates: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
            return ['start_date' => null, 'end_date' => null];
        }
    }

    private function _mergeGuestCart($oldSessionId)
    {
        $userId = auth()->id();

        if (!$userId) {
            Log::warning('Attempted to merge guest cart but user is not authenticated');
            return;
        }

        $currentSessionId = session()->getId();

        Log::info('Starting guest cart merge', [
            'user_id' => $userId,
            'old_session_id' => $oldSessionId,
            'current_session_id' => $currentSessionId
        ]);

        // Get all guest cart items for the old session ID (primary search)
        $guestItemsByOldSession = Cart::where('session_id', $oldSessionId)
            ->whereNull('user_id')
            ->get();

        Log::info('Guest cart query by old session', [
            'old_session_id' => $oldSessionId,
            'items_found' => $guestItemsByOldSession->count(),
            'items' => $guestItemsByOldSession->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_type' => $item->product_type,
                    'session_id' => $item->session_id,
                    'user_id' => $item->user_id
                ];
            })->toArray()
        ]);

        // Also check current session ID if it's different (fallback)
        $guestItemsByCurrentSession = collect();
        if ($currentSessionId !== $oldSessionId) {
            $guestItemsByCurrentSession = Cart::where('session_id', $currentSessionId)
                ->whereNull('user_id')
                ->get();

            Log::info('Guest cart query by current session', [
                'current_session_id' => $currentSessionId,
                'items_found' => $guestItemsByCurrentSession->count()
            ]);
        }

        // Merge both collections and remove duplicates by cart item ID
        $guestItems = $guestItemsByOldSession->merge($guestItemsByCurrentSession)
            ->unique('id');

        Log::info('Guest cart items found', [
            'user_id' => $userId,
            'old_session_items' => $guestItemsByOldSession->count(),
            'current_session_items' => $guestItemsByCurrentSession->count(),
            'total_items' => $guestItems->count(),
            'session_ids' => $guestItems->pluck('session_id')->unique()->toArray()
        ]);

        if ($guestItems->isNotEmpty()) {
            Log::info('Merging guest cart to user cart', [
                'user_id' => $userId,
                'old_session_id' => $oldSessionId,
                'guest_items_count' => $guestItems->count()
            ]);

            foreach ($guestItems as $item) {
                // Check if user already has this exact item (same product_id, product_type, and price_id)
                $existingCartItem = Cart::where('user_id', $userId)
                    ->where('product_id', $item->product_id)
                    ->where('product_type', $item->product_type)
                    ->where('price_id', $item->price_id)
                    ->first();

                if ($existingCartItem) {
                    // If item exists, add the quantities together
                    $existingCartItem->increment('quantity', $item->quantity);
                    Log::info('Merged guest cart item - updated existing item', [
                        'cart_item_id' => $existingCartItem->id,
                        'product_id' => $item->product_id,
                        'added_quantity' => $item->quantity,
                        'new_quantity' => $existingCartItem->quantity
                    ]);
                } else {
                    // If item doesn't exist, create it with the guest cart quantity
                    Cart::create([
                        'user_id' => $userId,
                        'session_id' => null, // Clear session_id for user cart items
                        'product_id' => $item->product_id,
                        'product_type' => $item->product_type,
                        'quantity' => $item->quantity,
                        'price_id' => $item->price_id,
                    ]);
                    Log::info('Merged guest cart item - created new item', [
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity
                    ]);
                }

                // Remove the guest cart item
                $item->delete();
            }

            Log::info('Guest cart merge completed', [
                'user_id' => $userId,
                'items_merged' => $guestItems->count()
            ]);
        }
    }
}
