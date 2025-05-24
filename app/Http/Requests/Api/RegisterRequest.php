<?php

namespace App\Http\Requests\Api;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {

        return
            [
                'first_name' => 'required',
                'last_name' => 'required',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|min:8|max:10',
                'choose_the_role' => 'required|integer',
            ];
    }
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {

            $chosenRole = request()->choose_the_role;

            $adminOrStudent = Role::whereIn('name', [config('constants.roles.ADMIN'), config('constants.roles.STUDENT')])->find(id: $chosenRole);

            if ($chosenRole && $adminOrStudent) {
                $validator->errors()->add('choose_the_role', "You can't register an admin or student through this URL.");
            }
        });
    }

}
