<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'sale_id', 'client_id', 'principal', 'total_due', 'amount_paid',
    'balance', 'start_date', 'due_date', 'status', 'notes',
])]
class Credit extends Model
{
    protected function casts(): array
    {
        return [
            'principal' => 'decimal:2',
            'total_due' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'balance' => 'decimal:2',
            'start_date' => 'date',
            'due_date' => 'date',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class)->orderBy('number');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['active', 'overdue']);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('status', 'overdue')
            ->orWhereHas('installments', fn (Builder $i) => $i
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->whereDate('due_date', '<', now()->toDateString())));
    }

    public function isSettled(): bool
    {
        return (float) $this->balance <= 0;
    }
}
