<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items'                 => ['required', 'array', 'min:1', 'max:20'],
            'items.*.ticket_id'     => ['required', 'integer', 'exists:tickets,id'],
            'items.*.quantity'      => ['required', 'integer', 'min:1', 'max:10'],
            'email'                 => ['required', 'email', 'max:255'],
            'payment_method'        => ['required', 'in:qrph,manual'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'            => 'Your cart is empty.',
            'items.min'                 => 'Your cart is empty.',
            'items.*.ticket_id.exists'  => 'One or more tickets no longer exist.',
            'items.*.quantity.min'      => 'Quantity must be at least 1.',
            'items.*.quantity.max'      => 'Quantity cannot exceed 10 per ticket tier.',
        ];
    }
}
