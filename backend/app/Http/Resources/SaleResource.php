<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'client_id' => $this->client_id,
            'client' => $this->whenLoaded('client', fn () => $this->client?->only(['id', 'code', 'name', 'phone', 'balance'])),
            'user' => $this->whenLoaded('user', fn () => $this->user?->only(['id', 'name'])),
            'date' => $this->date?->toDateString(),
            'type' => $this->type,
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'total' => (float) $this->total,
            'paid_amount' => (float) $this->paid_amount,
            'status' => $this->status,
            'due_date' => $this->due_date?->toDateString(),
            'notes' => $this->notes,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product' => $item->relationLoaded('product') ? $item->product?->only(['id', 'sku', 'name_fr', 'name_ar']) : null,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'discount' => (float) $item->discount,
                'total' => (float) $item->total,
            ])),
            'credit' => $this->whenLoaded('credit', fn () => $this->credit ? new CreditResource($this->credit) : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
