<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(private readonly CreditService $credits) {}

    /**
     * Crée une vente (comptant ou crédit) avec ses lignes, le stock et l'échéancier.
     *
     * @param  array{
     *   client_id?: int|null,
     *   date?: string,
     *   type: 'cash'|'credit',
     *   discount?: float,
     *   items: array<int, array{product_id:int, quantity:float, unit_price?:float, discount?:float}>,
     *   payment?: array{amount?:float, method?:string, reference?:string},
     *   installments?: array{count?:int, first_due_date?:string, interval_days?:int}
     * }  $data
     */
    public function create(array $data, User $user): Sale
    {
        return DB::transaction(function () use ($data, $user) {
            $type = $data['type'] ?? 'cash';
            $client = isset($data['client_id']) ? Client::lockForUpdate()->find($data['client_id']) : null;

            $lines = [];
            $subtotal = 0.0;

            foreach ($data['items'] as $row) {
                /** @var Product $product */
                $product = Product::lockForUpdate()->findOrFail($row['product_id']);
                $qty = round((float) $row['quantity'], 2);
                $trackStock = (float) $product->stock_qty > 0;

                if (! $product->is_active) {
                    throw ValidationException::withMessages([
                        'items' => __('Produit désactivé : :product.', ['product' => $product->name_fr]),
                    ]);
                }

                if ($qty <= 0) {
                    throw ValidationException::withMessages(['items' => __('Quantité invalide.')]);
                }

                if ($trackStock && (float) $product->stock_qty < $qty) {
                    throw ValidationException::withMessages([
                        'items' => __('Stock insuffisant pour : :product.', ['product' => $product->name_fr]),
                    ]);
                }

                $unitPrice = isset($row['unit_price']) ? round((float) $row['unit_price'], 2) : (float) $product->sale_price;
                $lineDiscount = round((float) ($row['discount'] ?? 0), 2);
                $lineTotal = round(($qty * $unitPrice) - $lineDiscount, 2);

                $lines[] = ['product' => $product, 'qty' => $qty, 'unit_price' => $unitPrice, 'discount' => $lineDiscount, 'total' => $lineTotal, 'track_stock' => $trackStock];
                $subtotal += $lineTotal;
            }

            $discount = round((float) ($data['discount'] ?? 0), 2);
            $total = round(max(0, $subtotal - $discount), 2);

            if ($type === 'credit' && ! $client) {
                throw ValidationException::withMessages(['client_id' => __('Un client est obligatoire pour une vente à crédit.')]);
            }

            if ($type === 'credit' && $client) {
                $this->credits->assertCanTakeCredit($client, $total);
            }

            $sale = Sale::create([
                'number' => $this->nextNumber(),
                'client_id' => $client?->id,
                'user_id' => $user->id,
                'date' => $data['date'] ?? now()->toDateString(),
                'type' => $type,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'paid_amount' => 0,
                'status' => 'unpaid',
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                $sale->items()->create([
                    'product_id' => $line['product']->id,
                    'quantity' => $line['qty'],
                    'unit_price' => $line['unit_price'],
                    'discount' => $line['discount'],
                    'total' => $line['total'],
                ]);

                if ($line['track_stock']) {
                    $line['product']->decrement('stock_qty', $line['qty']);

                    StockMovement::create([
                        'product_id' => $line['product']->id,
                        'type' => 'out',
                        'quantity' => $line['qty'],
                        'unit_cost' => $line['product']->purchase_price,
                        'reason' => 'Vente '.$sale->number,
                        'reference_type' => Sale::class,
                        'reference_id' => $sale->id,
                        'user_id' => $user->id,
                    ]);
                }
            }

            if ($type === 'cash') {
                $paid = round((float) ($data['payment']['amount'] ?? $total), 2);
                $paid = min($paid, $total);

                $sale->update([
                    'paid_amount' => $paid,
                    'status' => $paid >= $total - 0.009 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
                ]);

                if ($paid > 0) {
                    app(PaymentService::class)->recordCashSale($sale, $paid, $data['payment']['method'] ?? 'cash', $data['payment']['reference'] ?? null, $user);
                }
            } else {
                $cfg = $data['installments'] ?? [];
                $this->credits->createForSale(
                    $sale->id,
                    $client,
                    $total,
                    (int) ($cfg['count'] ?? 1),
                    isset($cfg['first_due_date']) ? Carbon::parse($cfg['first_due_date']) : null,
                    (int) ($cfg['interval_days'] ?? 30),
                );

                $client->increment('balance', $total);
            }

            return $sale->load(['items.product', 'client', 'credit.installments']);
        });
    }

    public function nextNumber(): string
    {
        $prefix = 'VTE-'.now()->format('Ymd').'-';
        $last = Sale::withTrashed()->where('number', 'like', $prefix.'%')->orderByDesc('number')->value('number');
        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
