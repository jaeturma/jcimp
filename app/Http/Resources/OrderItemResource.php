<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'ticket'   => new TicketResource($this->whenLoaded('ticket')),
            'quantity' => $this->quantity,
            'price'    => (float) $this->price,
            'subtotal' => $this->subtotal(),
        ];
    }
}
