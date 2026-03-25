<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitManualPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_reference'    => ['required', 'string', 'exists:orders,reference'],
            'proof_image'        => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            // Transaction details are optional if OCR extraction is enabled
            // If OCR fails, user must provide these
            'transaction_number' => ['nullable', 'string', 'max:100'],
            'transaction_amount' => ['nullable', 'numeric', 'min:0.01'],
        ];
    }

    public function messages(): array
    {
        return [
            'proof_image.mimes'   => 'Payment proof must be JPG, PNG, or PDF.',
            'proof_image.max'     => 'File may not exceed 10MB.',
            'transaction_number.max' => 'Transaction number must be 100 characters or less.',
            'transaction_amount.min' => 'Transaction amount must be greater than zero.',
        ];
    }

    protected function passedValidation(): void
    {
        // If both transaction fields are empty, they will be populated by OCR
        // If OCR fails, admin will need to manually verify
        if (empty($this->transaction_number) && empty($this->transaction_amount)) {
            // Don't fail validation here - let the service try OCR
            return;
        }
    }
}
