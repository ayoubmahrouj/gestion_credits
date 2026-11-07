<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Credit;
use App\Models\Installment;
use App\Models\Product;
use App\Support\XlsxWriter;
use Illuminate\Support\Carbon;

class ExportService
{
    public function __construct(private readonly ReportService $reports) {}

    /**
     * Construit le classeur Excel (une feuille par domaine).
     */
    public function workbook(array $filters): XlsxWriter
    {
        $scope = $filters['scope'] ?? 'all';
        $writer = new XlsxWriter();

        $writer->addSheet('Résumé', ['Indicateur', 'Valeur'], $this->summaryRows($filters));

        if ($this->includes($scope, 'clients')) {
            $writer->addSheet(
                'Clients',
                ['Code', 'Nom', 'Type', 'Téléphone', 'WhatsApp', 'Email', 'Ville', 'Limite', 'Encours', 'Disponible', 'Score', 'Statut', 'Actif', 'Créé le'],
                $this->clientRows(),
                [7, 8, 9],
            );
        }

        if ($this->includes($scope, 'products')) {
            $writer->addSheet(
                'Produits',
                ['Référence', 'Marque', 'Catégorie', 'Désignation FR', 'Désignation AR', 'Description', 'Prix achat', 'Prix vente', 'Stock', 'Stock min', 'Actif'],
                $this->productRows(),
                [6, 7],
            );
        }

        if ($this->includes($scope, 'sales')) {
            $writer->addSheet(
                'Ventes',
                ['N° vente', 'Date', 'Client', 'Total', 'Type', 'Payé', 'Reste', 'Statut', 'Code client', 'Sous-total', 'Remise', 'Vendeur'],
                $this->saleRows($filters),
                [3, 5, 6, 9, 10],
            );

            $writer->addSheet(
                'Lignes de vente',
                ['N° vente', 'Produit', 'Qté', 'Prix unitaire', 'Total', 'Date', 'Référence', 'Marque', 'Remise', 'Prix achat', 'Marge'],
                $this->saleItemRows($filters),
                [3, 4, 8, 9, 10],
            );
        }

        if ($this->includes($scope, 'payments')) {
            $writer->addSheet(
                'Encaissements',
                ['Date', 'Client', 'Code client', 'Montant', 'Méthode', 'Référence', 'Reçu par', 'Notes'],
                $this->paymentRows($filters),
                [3],
            );
        }

        if ($this->includes($scope, 'credits')) {
            $writer->addSheet(
                'Crédits',
                ['Client', 'Code client', 'N° vente', 'Principal', 'Total dû', 'Payé', 'Solde', 'Début', 'Échéance', 'Statut'],
                $this->creditRows($filters),
                [3, 4, 5, 6],
            );

            $writer->addSheet(
                'Échéances',
                ['Client', 'N° vente', 'N° échéance', 'Date d\'échéance', 'Montant', 'Payé', 'Reste', 'Statut'],
                $this->installmentRows($filters),
                [4, 5, 6],
            );
        }

        return $writer;
    }

    private function includes(string $scope, string $sheet): bool
    {
        return $scope === 'all' || $scope === $sheet;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function summaryRows(array $filters): array
    {
        $summary = $this->reports->summary($filters);
        $sales = $summary['sales'];
        $payments = $summary['payments'];

        return [
            ['Période', $summary['period']['from'].' → '.$summary['period']['to']],
            ['Nombre de jours', $summary['period']['days']],
            ['Nombre de ventes', $sales['count']],
            ['Chiffre d\'affaires', $sales['total']],
            ['Ventes comptant', $sales['cash']],
            ['Ventes à crédit', $sales['credit']],
            ['Total des remises', $sales['discount']],
            ['Panier moyen', $sales['avg_basket']],
            ['Encaissements', $payments['total']],
            ['Nombre d\'encaissements', $payments['count']],
            ['Marge estimée', $summary['margin']['margin']],
            ['Taux de marge (%)', $summary['margin']['rate']],
            ['Créances en cours', $summary['receivables']['balance']],
            ['Créances en retard', $summary['receivables']['overdue']],
            ['Clients actifs', $summary['clients']['active']],
            ['Nouveaux clients', $summary['clients']['new']],
            ['Produits actifs', $summary['stock']['products']],
            ['Valeur du stock (achat)', $summary['stock']['value_purchase']],
            ['Produits en stock bas', $summary['stock']['low']],
            ['Produits en rupture', $summary['stock']['out']],
        ];
    }

    private function clientRows(): array
    {
        return Client::query()
            ->orderBy('code')
            ->get()
            ->map(fn (Client $client) => [
                $client->code,
                $client->name,
                $client->type === 'company' ? 'Société' : 'Particulier',
                $client->phone,
                $client->whatsapp,
                $client->email,
                $client->city,
                (float) $client->credit_limit,
                (float) $client->balance,
                max(0, (float) $client->credit_limit - (float) $client->balance),
                (int) $client->score,
                $client->status,
                $client->is_active ? 'Oui' : 'Non',
                $client->created_at?->format('d/m/Y'),
            ])
            ->all();
    }

    private function productRows(): array
    {
        return Product::withTrashed()
            ->with(['brand:id,name', 'category:id,name'])
            ->orderBy('sku')
            ->get()
            ->map(fn (Product $product) => [
                $product->sku,
                $product->brand?->name,
                $product->category?->name,
                $product->name_fr,
                $product->name_ar,
                $product->description,
                (float) $product->purchase_price,
                (float) $product->sale_price,
                (float) $product->stock_qty,
                (float) $product->stock_min,
                $product->is_active ? 'Oui' : 'Non',
            ])
            ->all();
    }

    private function saleRows(array $filters): array
    {
        return $this->reports->salesQuery($filters)
            ->with(['client:id,code,name', 'user:id,name'])
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->map(fn ($sale) => [
                $sale->number,
                $sale->date?->format('d/m/Y'),
                $sale->client?->name ?? '—',
                (float) $sale->total,
                $sale->type === 'credit' ? 'Crédit' : 'Comptant',
                (float) $sale->paid_amount,
                max(0, (float) $sale->total - (float) $sale->paid_amount),
                $this->statusLabel($sale->status),
                $sale->client?->code,
                (float) $sale->subtotal,
                (float) $sale->discount,
                $sale->user?->name,
            ])
            ->all();
    }

    private function saleItemRows(array $filters): array
    {
        return $this->reports->itemsQuery($filters)
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->select([
                'sales.number as sale_number',
                'sales.date as sale_date',
                'products.name_fr',
                'products.sku',
                'brands.name as brand_name',
                'sale_items.quantity',
                'sale_items.unit_price',
                'sale_items.discount',
                'sale_items.total',
                'products.purchase_price',
            ])
            ->orderBy('sales.date')
            ->orderBy('sale_items.id')
            ->get()
            ->map(fn ($item) => [
                $item->sale_number,
                $item->name_fr,
                (float) $item->quantity,
                (float) $item->unit_price,
                (float) $item->total,
                Carbon::parse($item->sale_date)->format('d/m/Y'),
                $item->sku,
                $item->brand_name,
                (float) $item->discount,
                (float) $item->purchase_price,
                round((float) $item->total - ((float) $item->quantity * (float) $item->purchase_price), 2),
            ])
            ->all();
    }

    private function paymentRows(array $filters): array
    {
        return $this->reports->paymentsQuery($filters)
            ->with(['client:id,code,name', 'receiver:id,name'])
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get()
            ->map(fn ($payment) => [
                $payment->paid_at?->format('d/m/Y H:i'),
                $payment->client?->name ?? '—',
                $payment->client?->code,
                (float) $payment->amount,
                $this->methodLabel($payment->method),
                $payment->reference,
                $payment->receiver?->name,
                $payment->notes,
            ])
            ->all();
    }

    private function creditRows(array $filters): array
    {
        return Credit::query()
            ->with(['client:id,code,name', 'sale:id,number'])
            ->whereDate('start_date', '>=', $filters['from'])
            ->whereDate('start_date', '<=', $filters['to'])
            ->when($filters['client_id'] ?? null, fn ($q, $v) => $q->where('client_id', $v))
            ->orderBy('start_date')
            ->orderBy('id')
            ->get()
            ->map(fn (Credit $credit) => [
                $credit->client?->name ?? '—',
                $credit->client?->code,
                $credit->sale?->number,
                (float) $credit->principal,
                (float) $credit->total_due,
                (float) $credit->amount_paid,
                (float) $credit->balance,
                $credit->start_date?->format('d/m/Y'),
                $credit->due_date?->format('d/m/Y'),
                $this->creditStatusLabel($credit->status),
            ])
            ->all();
    }

    private function installmentRows(array $filters): array
    {
        return Installment::query()
            ->with(['credit:id,sale_id,client_id', 'credit.client:id,code,name', 'credit.sale:id,number'])
            ->whereDate('due_date', '>=', $filters['from'])
            ->whereDate('due_date', '<=', $filters['to'])
            ->when($filters['client_id'] ?? null, fn ($q, $v) => $q->whereHas('credit', fn ($c) => $c->where('client_id', $v)))
            ->orderBy('due_date')
            ->orderBy('id')
            ->get()
            ->map(fn (Installment $installment) => [
                $installment->credit?->client?->name ?? '—',
                $installment->credit?->sale?->number,
                $installment->number,
                $installment->due_date?->format('d/m/Y'),
                (float) $installment->amount,
                (float) $installment->paid_amount,
                max(0, (float) $installment->amount - (float) $installment->paid_amount),
                $this->installmentStatusLabel($installment->status),
            ])
            ->all();
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'paid' => 'Payée',
            'partial' => 'Partielle',
            'unpaid' => 'Impayée',
            'cancelled' => 'Annulée',
            default => $status,
        };
    }

    private function methodLabel(string $method): string
    {
        return match ($method) {
            'cash' => 'Espèces',
            'bank' => 'Virement',
            'cheque' => 'Chèque',
            'card' => 'Carte',
            default => 'Autre',
        };
    }

    private function creditStatusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'En cours',
            'settled' => 'Soldé',
            'overdue' => 'En retard',
            'cancelled' => 'Annulé',
            default => $status,
        };
    }

    private function installmentStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'À payer',
            'partial' => 'Partielle',
            'paid' => 'Payée',
            'overdue' => 'En retard',
            default => $status,
        };
    }
}
