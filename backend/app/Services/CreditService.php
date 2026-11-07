<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Credit;
use App\Models\Installment;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CreditService
{
    /**
     * Vérifie la limite de crédit avant toute vente à crédit.
     */
    public function assertCanTakeCredit(Client $client, float $amount): void
    {
        if ($client->status === 'blocked' || ! $client->is_active) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'client_id' => __('Ce client est bloqué : vente à crédit interdite.'),
            ]);
        }

        if ((float) $client->balance + $amount > (float) $client->credit_limit) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'client_id' => __('Limite de crédit dépassée (disponible : :available DH).', [
                    'available' => number_format($client->availableCredit(), 2, ',', ' '),
                ]),
            ]);
        }
    }

    /**
     * Crée le crédit d'une vente + son échéancier.
     */
    public function createForSale(
        int $saleId,
        Client $client,
        float $total,
        ?int $installmentsCount = null,
        ?CarbonInterface $firstDueDate = null,
        int $intervalDays = 30,
    ): Credit {
        $count = max(1, (int) ($installmentsCount ?? 1));
        $firstDue = $firstDueDate ? Carbon::parse($firstDueDate) : Carbon::now()->addDays($intervalDays);

        $credit = Credit::create([
            'sale_id' => $saleId,
            'client_id' => $client->id,
            'principal' => $total,
            'total_due' => $total,
            'amount_paid' => 0,
            'balance' => $total,
            'start_date' => Carbon::now()->toDateString(),
            'due_date' => $firstDue->copy()->addDays($intervalDays * ($count - 1))->toDateString(),
            'status' => 'active',
        ]);

        $this->generateSchedule($credit, $count, $firstDue, $intervalDays);

        return $credit;
    }

    /**
     * Génère les échéances (le dernier absorbe les arrondis).
     */
    public function generateSchedule(Credit $credit, int $count, CarbonInterface $firstDue, int $intervalDays = 30): void
    {
        $count = max(1, $count);
        $totalCents = (int) round((float) $credit->total_due * 100);
        $base = intdiv($totalCents, $count);
        $remainder = $totalCents - ($base * $count);

        DB::transaction(function () use ($credit, $count, $firstDue, $intervalDays, $base, $remainder) {
            $credit->installments()->delete();

            for ($i = 0; $i < $count; $i++) {
                $cents = $base + ($i === $count - 1 ? $remainder : 0);

                Installment::create([
                    'credit_id' => $credit->id,
                    'number' => $i + 1,
                    'due_date' => Carbon::parse($firstDue)->addDays($intervalDays * $i)->toDateString(),
                    'amount' => $cents / 100,
                    'paid_amount' => 0,
                    'status' => 'pending',
                ]);
            }
        });
    }

    /**
     * Met à jour le statut d'un crédit (settled / overdue / active).
     */
    public function refreshStatus(Credit $credit): void
    {
        $credit->refresh();

        $hasOverdue = $credit->installments()
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->whereDate('due_date', '<', now()->toDateString())
            ->exists();

        $status = 'active';
        if ((float) $credit->balance <= 0.009) {
            $status = 'settled';
        } elseif ($hasOverdue || ($credit->due_date && $credit->due_date->isPast())) {
            $status = 'overdue';
        }

        if ($credit->status !== $status) {
            $credit->update(['status' => $status]);
        }
    }

    /**
     * Marque en retard toutes les échéances échues non soldées.
     */
    public function flagOverdueInstallments(): int
    {
        return Installment::whereIn('status', ['pending', 'partial'])
            ->whereDate('due_date', '<', now()->toDateString())
            ->update(['status' => 'overdue']);
    }
}
