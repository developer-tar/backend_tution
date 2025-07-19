<?php


namespace App\Rules;

use App\Models\Role;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

class ValidRoleLogin implements Rule
{
    public function passes($attribute, $value)
    {
        $roleIds = Role::whereNot('name', config('constants.roles.ADMIN'))->pluck('id')->toArray();

        // Ensure role ID is not 1 and exists in the roles table
        return in_array($value, $roleIds);
    }

    public function message()
    {
        return 'You are not authorized to login with this role.';
    }
}
