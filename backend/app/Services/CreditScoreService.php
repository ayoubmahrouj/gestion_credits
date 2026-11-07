<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Installment;
use Illuminate\Support\Carbon;

class CreditScoreService
{
    /**
     * Score de paiement 0–100 calculé par règles :
     *  + régularité des paiements à temps
     *  - retards et ancienneté des retards
     *  - dépassement de limite
     *  + fidélité (ancienneté client)
     */
    public function compute(Client $client): int
    {
        $installments = Installment::query()
            ->whereHas('credit', fn ($q) => $q->where('client_id', $client->id))
            ->get();

        if ($installments->isEmpty()) {
            return 50;
        }

        $total = $installments->count();

        $paidOnTime = $installments->filter(function (Installment $i) {
            return $i->status === 'paid'
                && $i->paid_at
                && $i->paid_at->lte($i->due_date->copy()->endOfDay());
        })->count();

        $lateOpen = $installments->filter(fn (Installment $i) => $i->status !== 'paid' && $i->due_date->isPast());

        $maxLateDays = (int) $lateOpen->max(fn (Installment $i) => $i->due_date->diffInDays(now())) ?: 0;

        $score = 60.0;
        $score += ($paidOnTime / max(1, $total)) * 25;          // jusqu'à +25
        $score -= min(40, $lateOpen->count() * 8);              // -8 par échéance en retard
        $score -= min(25, $maxLateDays * 0.5);                  // -0.5 par jour de retard (max 25)

        if ((float) $client->balance > (float) $client->credit_limit) {
            $score -= 10;
        }

        $months = $client->created_at ? Carbon::parse($client->created_at)->diffInMonths(now()) : 0;
        if ($months >= 6) {
            $score += 5;
        }

        return (int) max(0, min(100, round($score)));
    }

    public function label(int $score): string
    {
        return match (true) {
            $score >= 80 => 'excellent',
            $score >= 60 => 'good',
            $score >= 40 => 'average',
            $score >= 20 => 'risky',
            default => 'bad',
        };
    }
}
