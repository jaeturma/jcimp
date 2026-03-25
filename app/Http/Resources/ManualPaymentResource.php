<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManualPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'order_id'            => $this->order_id,
            'proof_image'         => $this->proof_image,
            'transaction_number'  => $this->transaction_number,
            'transaction_amount'  => $this->transaction_amount ? (float) $this->transaction_amount : null,
            'status'              => $this->status,
            'ocr_extracted'       => (bool) $this->ocr_extracted,
            'ocr_confidence'      => $this->ocr_confidence,
            'ocr_text'            => $this->when(
                $request->user()?->is_admin,
                fn () => $this->ocr_text
            ),
            'reviewed_at'         => $this->reviewed_at?->toISOString(),
            'rejection_reason'    => $this->rejection_reason,
            'created_at'          => $this->created_at?->toISOString(),
            'order'               => new OrderResource($this->whenLoaded('order')),
            'reviewer'            => $this->whenLoaded('reviewer', fn () => [
                'id'   => $this->reviewer->id,
                'name' => $this->reviewer->name,
            ]),
        ];
    }
}
