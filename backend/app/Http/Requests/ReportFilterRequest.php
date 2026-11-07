<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'client_id' => ['nullable', 'integer'],
            'brand_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'type' => ['nullable', 'in:cash,credit'],
            'method' => ['nullable', 'in:cash,bank,cheque,card,other'],
            'status' => ['nullable', 'in:paid,partial,unpaid,cancelled'],
            'scope' => ['nullable', 'in:all,clients,products,sales,payments,credits'],
        ];
    }

    /**
     * Filtres normalisés (dates par défaut : mois en cours).
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $from = $this->filled('from')
            ? Carbon::parse($this->input('from'))->toDateString()
            : now()->startOfMonth()->toDateString();

        $to = $this->filled('to')
            ? Carbon::parse($this->input('to'))->toDateString()
            : now()->toDateString();

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [
            'from' => $from,
            'to' => $to,
            'client_id' => $this->integer('client_id') ?: null,
            'brand_id' => $this->integer('brand_id') ?: null,
            'category_id' => $this->integer('category_id') ?: null,
            'user_id' => $this->integer('user_id') ?: null,
            'type' => $this->input('type') ?: null,
            'method' => $this->input('method') ?: null,
            'status' => $this->input('status') ?: null,
            'scope' => $this->input('scope') ?: 'all',
        ];
    }
}
