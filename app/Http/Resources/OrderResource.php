<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'reference'         => $this->reference,
            'email'             => $this->email,
            'status'            => $this->status,
            'payment_method'    => $this->payment_method,
            'total_amount'      => (float) $this->total_amount,
            'gateway_reference' => $this->gateway_reference,
            'created_at'        => $this->created_at?->toISOString(),
            'items'             => OrderItemResource::collection($this->whenLoaded('items')),
            'manual_payment'    => new ManualPaymentResource($this->whenLoaded('manualPayment')),
            'tickets_issued'    => TicketIssuedResource::collection($this->whenLoaded('issuedTickets')),
        ];
    }
}
