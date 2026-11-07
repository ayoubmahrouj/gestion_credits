<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Credit;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private readonly CreditService $credits) {}

    /**
     * Enregistre un versement sur les crédits du client (FIFO : plus ancienne échéance d'abord).
     * Idempotent si `idempotency_key` est fourni.
     */
    public function register(array $data, User $user): Payment
    {
        return DB::transaction(function () use ($data, $user) {
            if (! empty($data['idempotency_key'])) {
                $existing = Payment::where('idempotency_key', $data['idempotency_key'])->first();
                if ($existing) {
                    return $existing->load('allocations');
                }
            }

            $amount = round((float) $data['amount'], 2);
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => __('Le montant doit être supérieur à zéro.')]);
            }

            /** @var Client $client */
            $client = Client::lockForUpdate()->findOrFail($data['client_id']);

            $payment = Payment::create([
                'client_id' => $client->id,
                'credit_id' => $data['credit_id'] ?? null,
                'amount' => $amount,
                'method' => $data['method'] ?? 'cash',
                'reference' => $data['reference'] ?? null,
                'paid_at' => isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : now(),
                'received_by' => $user->id,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $remaining = $amount;

            $query = Installment::query()
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->whereHas('credit', function ($q) use ($client, $data) {
                    $q->where('client_id', $client->id)->whereIn('status', ['active', 'overdue']);
                    if (! empty($data['credit_id'])) {
                        $q->whereKey($data['credit_id']);
                    }
                })
                ->orderBy('due_date')
                ->orderBy('number')
                ->lockForUpdate();

            $touchedCredits = [];

            foreach ($query->get() as $installment) {
                if ($remaining <= 0) {
                    break;
                }

                $due = $installment->remaining();
                if ($due <= 0) {
                    continue;
                }

                $apply = round(min($due, $remaining), 2);
                $newPaid = round((float) $installment->paid_amount + $apply, 2);
                $isPaid = $newPaid >= (float) $installment->amount - 0.009;

                $installment->update([
                    'paid_amount' => $newPaid,
                    'status' => $isPaid ? 'paid' : 'partial',
                    'paid_at' => $isPaid ? now() : null,
                ]);

                $payment->allocations()->create([
                    'installment_id' => $installment->id,
                    'amount' => $apply,
                ]);

                $credit = $installment->credit;
                $credit->update([
                    'amount_paid' => round((float) $credit->amount_paid + $apply, 2),
                    'balance' => round(max(0, (float) $credit->balance - $apply), 2),
                ]);

                $touchedCredits[$credit->id] = $credit;
                $remaining = round($remaining - $apply, 2);
            }

            foreach ($touchedCredits as $credit) {
                $this->credits->refreshStatus($credit);
            }

            // Le trop-perçu reste en avance (solde négatif = avoir client).
            $client->update(['balance' => round((float) $client->balance - $amount, 2)]);

            // Mise à jour du score de crédit.
            $client->update(['score' => app(CreditScoreService::class)->compute($client)]);

            return $payment->load('allocations.installment');
        });
    }

    /**
     * Enregistre l'encaissement d'une vente comptant (journal de caisse).
     */
    public function recordCashSale(Sale $sale, float $amount, string $method, ?string $reference, User $user): Payment
    {
        return Payment::create([
            'client_id' => $sale->client_id,
            'credit_id' => null,
            'amount' => round($amount, 2),
            'method' => $method,
            'reference' => $reference,
            'paid_at' => now(),
            'received_by' => $user->id,
            'notes' => 'Vente comptant '.$sale->number,
        ]);
    }
}
