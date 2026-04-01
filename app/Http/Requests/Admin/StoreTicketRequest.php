<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('create tickets') ?? false;
    }

    public function rules(): array
    {
        return [
            'event_id'               => ['required', 'integer', 'exists:events,id'],
            'name'                   => ['required', 'string', 'max:255'],
            'price'                  => ['required', 'numeric', 'min:0'],
            'total_quantity'         => ['required', 'integer', 'min:1'],
            'type'                   => ['required', 'in:regular,student'],
            'max_per_user'           => ['required', 'integer', 'min:1', 'max:10'],
            'requires_verification'  => ['boolean'],
        ];
    }
}
