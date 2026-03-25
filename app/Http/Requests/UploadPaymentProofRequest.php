<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadPaymentProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_reference' => ['required', 'string', 'exists:orders,reference'],
            'proof_image'     => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'proof_image.mimes' => 'Payment proof must be JPG, PNG, or PDF.',
            'proof_image.max'   => 'File may not exceed 10MB.',
        ];
    }
}
