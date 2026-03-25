<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'event_id'             => $this->event_id,
            'name'                 => $this->name,
            'price'                => (float) $this->price,
            'type'                 => $this->type,
            'total_quantity'       => $this->total_quantity,
            'reserved_quantity'    => $this->reserved_quantity,
            'sold_quantity'        => $this->sold_quantity,
            'available'            => $this->availableQuantity(),
            'sold_out'             => $this->isSoldOut(),
            'max_per_user'         => $this->max_per_user,
            'requires_verification'=> $this->requires_verification,
            'revenue'              => number_format($this->price * $this->sold_quantity, 2),
        ];
    }
}
