<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'client' => $this->whenLoaded('client', fn () => $this->client?->only(['id', 'code', 'name', 'phone', 'balance'])),
            'credit_id' => $this->credit_id,
            'credit' => $this->whenLoaded('credit', fn () => $this->credit ? [
                'id' => $this->credit->id,
                'sale_id' => $this->credit->sale_id,
                'sale_number' => $this->credit->sale?->number,
                'balance' => (float) $this->credit->balance,
                'status' => $this->credit->status,
            ] : null),
            'amount' => (float) $this->amount,
            'method' => $this->method,
            'reference' => $this->reference,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'received_by' => $this->whenLoaded('receiver', fn () => $this->receiver?->only(['id', 'name'])),
            'notes' => $this->notes,
            'allocations' => $this->whenLoaded('allocations', fn () => $this->allocations->map(fn ($a) => [
                'installment_id' => $a->installment_id,
                'amount' => (float) $a->amount,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
