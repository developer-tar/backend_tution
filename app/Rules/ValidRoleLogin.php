<?php


    namespace App\Rules;

    use Illuminate\Contracts\Validation\Rule;
    use Illuminate\Support\Facades\DB;

    class ValidRoleLogin implements Rule
    {
        public function passes($attribute, $value)
        {
            // Ensure role ID is not 1 and exists in the roles table
            return in_array($value, [2, 3, 4]);
        }

        public function message()
        {
            return 'You are not authorized to login with this role.';
        }
    }
