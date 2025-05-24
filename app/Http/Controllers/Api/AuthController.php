<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AdminLoginRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\Role;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

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
        return $this->authenticate($request);
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
                $success['token'] = $user->createToken('accessToken')->accessToken;
            }
            DB::commit();

            return sendResponse($success, 'User has been successfully created.', 201);
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
            if (Auth::attempt($credentials)) {
                $user = Auth::user();
                $role = $user->roles()->first();
                if (!$role) {
                    Log::error("Role not found", ['user' => $user]);
                    return sendError('Unauthorized', ['error' => 'Something went Wrong'], 500);
                }
                if($role && $request->filled('choose_the_role') && $role?->id != $request->choose_the_role){
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
}

