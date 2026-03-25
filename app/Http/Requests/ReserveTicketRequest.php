<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReserveTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ticket_id' => ['required', 'integer', 'exists:tickets,id'],
            'quantity'  => ['required', 'integer', 'min:1', 'max:10'],
            'email'     => ['required', 'email', 'max:255'],
        ];
    }
}
