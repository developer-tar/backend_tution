<?php

namespace App\Http\Requests\Api\Parent;

use App\Models\BillingInformation;
use App\Models\Paper;
use App\Models\PaperPurchase;
use App\Models\RequestedPaperToHome;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RequestPaperToHomeRequest extends FormRequest
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
        $parentId = auth()->id();
        
        return [
            'paper_id' => [
                'required',
                'integer',
                'exists:papers,id',
            ],
            'billing_information_id' => [
                'required',
                'integer',
                'exists:billing_informations,id',
                function ($attribute, $value, $fail) use ($parentId) {
                    $billingInfo = BillingInformation::where('id', $value)
                        ->where('parent_id', $parentId)
                        ->first();
                    
                    if (!$billingInfo) {
                        $fail('The selected billing information does not belong to you.');
                    }
                },
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $parentId = auth()->user()->id;
            $paperId = $this->input('paper_id');
            $billingInfoId = $this->input('billing_information_id');

            // Check if paper exists
            $paper = Paper::with('format')->find($paperId);
            if (!$paper) {
                $validator->errors()->add('paper_id', 'Paper not found.');
                return;
            }

            // Check if parent has purchased the paper (with paid status)
            $hasAccess = PaperPurchase::where('user_id', $parentId)
                ->where('paper_id', $paperId)
                ->where('payment_status', config('constants.stripe_payment_status.PAID'))
                ->exists();

            if (!$hasAccess) {
                $validator->errors()->add('paper_id', 'You do not have access to this paper. Please purchase it first.');
                return;
            }

            // Check paper format - only "physical" or "any" formats allowed
            $formatName = strtolower($paper->format->name ?? '');
            if (!in_array($formatName, ['physical', 'any'])) {
                $validator->errors()->add('paper_id', 'Only physical or any format papers can be requested for home delivery.');
                return;
            }

            // Note: Billing information ownership is already validated in rules() method
            // This check is redundant but kept for additional safety
            $billingInfo = BillingInformation::where('id', $billingInfoId)
                ->where('parent_id', $parentId)
                ->first();

            if (!$billingInfo) {
                $validator->errors()->add('billing_information_id', 'Billing information not found or does not belong to you.');
                return;
            }

            // Note: Duplicate requests are now allowed - they will update the existing record
            // This allows parents to change their billing information for the same paper request
            // The controller handles the update logic
        });
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'paper_id.required' => 'Paper ID is required.',
            'paper_id.integer' => 'Paper ID must be a valid number.',
            'paper_id.exists' => 'Paper not found.',
            'billing_information_id.required' => 'Billing information ID is required.',
            'billing_information_id.integer' => 'Billing information ID must be a valid number.',
            'billing_information_id.exists' => 'Billing information not found.',
        ];
    }
}
