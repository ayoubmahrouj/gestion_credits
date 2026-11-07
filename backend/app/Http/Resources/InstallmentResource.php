<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstallmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'due_date' => $this->due_date?->toDateString(),
            'amount' => (float) $this->amount,
            'paid_amount' => (float) $this->paid_amount,
            'remaining' => round($this->remaining(), 2),
            'status' => $this->status,
            'is_overdue' => $this->isOverdue(),
            'paid_at' => $this->paid_at?->toIso8601String(),
        ];
    }
}
