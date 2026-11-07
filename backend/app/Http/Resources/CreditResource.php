<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_id' => $this->sale_id,
            'client_id' => $this->client_id,
            'client' => $this->whenLoaded('client', fn () => $this->client?->only(['id', 'code', 'name', 'phone', 'score'])),
            'principal' => (float) $this->principal,
            'total_due' => (float) $this->total_due,
            'amount_paid' => (float) $this->amount_paid,
            'balance' => (float) $this->balance,
            'start_date' => $this->start_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'status' => $this->status,
            'days_overdue' => $this->when($this->due_date && $this->balance > 0, fn () => max(0, (int) $this->due_date->diffInDays(now(), false))),
            'installments' => InstallmentResource::collection($this->whenLoaded('installments')),
            'sale' => $this->whenLoaded('sale', fn () => $this->sale?->only(['id', 'number', 'date', 'total'])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
