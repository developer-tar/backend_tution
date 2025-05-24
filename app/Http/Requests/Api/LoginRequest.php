<?php

namespace App\Http\Requests\Api;

use App\Rules\ValidRoleLogin;
use Illuminate\Foundation\Http\FormRequest;
use App\Rules\ValidRole;
class LoginRequest extends FormRequest
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
                'email' => 'required|email|exists:users,email',
                'password' => 'required|min:8|max:10',
                'choose_the_role' => ['required', 'integer', new ValidRoleLogin()],
            ];
    }
}
