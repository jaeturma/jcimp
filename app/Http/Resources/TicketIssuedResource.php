<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketIssuedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'qr_code'         => $this->qr_code,
            'status'          => $this->status,
            'used_at'         => $this->used_at?->toISOString(),
            'holder_name'     => $this->holder_name,
            'holder_email'    => $this->holder_email,
            'ticket_card_url' => $this->ticket_card_url,
            'ticket'          => new TicketResource($this->whenLoaded('ticket')),
        ];
    }
}
