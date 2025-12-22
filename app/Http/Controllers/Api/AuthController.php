<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AdminLoginRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\Cart;
use App\Models\Role;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
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
                    Log::error("Role has not found", ['user' => $user]);
                    return sendError('Not Found', ['error' => 'User role not found.'], 404);
                }
                if ($role?->name != config('constants.roles.ADMIN')) {
                    return sendError('Unauthorized', ['error' => 'You are not authorized to access admin panel.'], 403);
                }
                if ($user->status == config('constants.statuses.APPROVED')) {
                    // Create token with role name as scope
                    $tokenResult = $user->createToken('accessToken', [$role?->name]);
                    
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
            Log::error("Validation error in admin login. Message => {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'data' => ['error' => $e->getMessage()],
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            $errorDetails = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ];
            
            Log::error("Error occur in admin login", $errorDetails);
            
            // Always show detailed error for debugging (can be changed to check config('app.debug') later)
            $errorMessage = "An error occurred: {$e->getMessage()}";
            if (config('app.debug')) {
                $errorMessage .= " in {$e->getFile()} on line {$e->getLine()}";
            }
            
            return sendError('Error', ['error' => $errorMessage], 500);
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
            Log::error("Failed to register  user. Message => {$e->getMessage()}, File => {$e->getFile()},  Line No => {$e->getLine()}, Error Code => {$e->getCode()}.");
            return sendResponse($success, 'Unable to create a new user.' . $e->getCode(), 500);
        }
    }
    public function authenticate(Request $request)
    {
        try {
            $credentials = $request->only('email', 'password');
            $oldSessionId = session()->getId(); // guest session

            if (Auth::attempt($credentials)) {
                $user = Auth::user();
                $role = $user->roles()->first();
                if (!$role) {
                    Log::error("Role has not found", ['user' => $user]);
                    return sendError('This user is not belong any role', ['error' => 'Something went Wrong'], 500);
                }


                if ($role && $request->filled('choose_the_role') && $role?->id != $request->choose_the_role) {
                    return sendError('Unauthorised', ['error' => "Credentails and user role has doesn't match"], 401);
                }
                $status = $user->status;
                if ($status == config('constants.statuses.APPROVED')) {

                    $user = [
                        'id' => $user->id,
                        'full_name' => $user->full_name,
                        'email' => $user->email,
                        'role' => $role?->name,
                        'access_token' => $user->createToken('accessToken', [$role?->name])->accessToken,
                    ];

                    if ($role->name == config('constants.roles.PARENT')) {

                        $this->_mergeGuestCart($oldSessionId);
                    }
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
            Log::error("Error occur login. Message => {$e->getMessage()}, File => {$e->getFile()},  Line No => {$e->getLine()}, Error Code => {$e->getCode()}.");
            return sendError('Error', ['error' => 'An error is occured.'], 500);
        }
    }
    private function _mergeGuestCart($oldSessionId)
    {
        $userId = auth()->id();

        $guestItems = Cart::where('session_id', $oldSessionId)->get();
        
        if ($guestItems->isNotEmpty()) {
            foreach ($guestItems as $item) {
                $cartItem = Cart::firstOrCreate(
                    [
                        'user_id' => $userId,
                        'product_id' => $item->product_id,
                        'price_id' => $item->price_id,
                    ],
                    [
                        'quantity' => 0, // default if new
                        'product_type' => $item->product_type,
                    ]
                );

                // Increment the quantity safely
                $cartItem->increment('quantity', $item->quantity);

                // Remove the guest cart item
                $item->delete();
            }
        }
    }

}
