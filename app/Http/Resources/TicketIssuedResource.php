<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketIssuedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'      => $this->id,
            'qr_code' => $this->qr_code,
            'status'  => $this->status,
            'used_at' => $this->used_at?->toISOString(),
            'ticket'  => new TicketResource($this->whenLoaded('ticket')),
        ];
    }
}
