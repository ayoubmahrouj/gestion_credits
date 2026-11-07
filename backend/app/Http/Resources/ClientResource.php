<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'ice' => $this->ice,
            'phone' => $this->phone,
            'whatsapp' => $this->whatsapp,
            'email' => $this->email,
            'address' => $this->address,
            'city' => $this->city,
            'credit_limit' => (float) $this->credit_limit,
            'balance' => (float) $this->balance,
            'available_credit' => round($this->availableCredit(), 2),
            'score' => (int) $this->score,
            'status' => $this->status,
            'is_active' => (bool) $this->is_active,
            'notes' => $this->notes,
            'credits_count' => $this->whenCounted('credits'),
            'sales_count' => $this->whenCounted('sales'),
            'created_at' => $this->created_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
