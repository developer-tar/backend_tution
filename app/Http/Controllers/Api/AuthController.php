<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
    public function login(LoginRequest $request)
    {
        try {

            $credentials = $request->only('email', 'password');
            if (Auth::attempt($credentials)) {
                $user = Auth::user();
                $status = $user->status;

                if ($status == config('constants.statuses.APPROVED')) {
                    $user = [
                        'id' => $user->id,
                        'full_name' => $user->full_name,
                        'email' => $user->email,
                        'access_token' => $user->createToken('accessToken')->accessToken,

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
                return sendError('Unauthorised', ['error' => 'Unauthorised'], 401);
            }
        } catch (Exception $e) {
            Log::error("Error occur login. Message => {$e->getMessage()}, File => {$e->getFile()},  Line No => {$e->getLine()}, Error Code => {$e->getCode()}.");
            return sendError('Error', ['error' => 'An error is occured.'], 500);
        }
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
                $role = $request->role;
                $roleQuery = Role::select('name')->where('name', config('constants.roles.' . $role))->firstOrFail();
                $user->roles()->attach($roleQuery['id'], ['created_at' => now(), 'updated_at' => now()]);

            }
            DB::commit();
            $success['full_name'] = $user->full_name;
            $success['token'] = $user->createToken('accessToken')->accessToken;
            return sendResponse($success, 'User has been successfully created.', 201);
        } catch (Exception $e) {
            DB::rollBack();
            $success['token'] = [];
            Log::error("Failed to register  user. Message => {$e->getMessage()}, File => {$e->getFile()},  Line No => {$e->getLine()}, Error Code => {$e->getCode()}.");
            return sendResponse($success, 'Unable to create a new user.' . $e->getCode(), 500);
        }
    }
}
