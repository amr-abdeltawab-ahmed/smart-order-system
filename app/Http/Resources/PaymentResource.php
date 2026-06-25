<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'order_id'          => $this->order_id,
            'payment_method'    => $this->payment_method,
            'payment_reference' => $this->payment_reference,
            'status'            => $this->status?->value ?? $this->status,
            'created_at'        => $this->created_at->toISOString(),
        ];
    }
}
