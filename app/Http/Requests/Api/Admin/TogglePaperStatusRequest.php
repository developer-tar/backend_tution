<?php

namespace App\Http\Requests\Api\Admin;

use App\Models\Paper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

class TogglePaperStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Check if user is authenticated
        $user = $this->user();
        if (!$user) {
            return false;
        }
        
        // Check if user has admin role using constants
        return $user->roles()
            ->where('name', config('constants.roles.ADMIN'))
            ->exists();
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:activate,deactivate'],
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'Action is required.',
            'action.in' => 'Action must be either "activate" or "deactivate".',
            'paper.exists' => 'The paper does not exist.',
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $paperId = $this->route('paper');
            
            // Check if paper exists
            $paper = Paper::find($paperId);
            
            if (!$paper) {
                $validator->errors()->add('paper', 'The paper does not exist.');
            }
        });
    }
}







