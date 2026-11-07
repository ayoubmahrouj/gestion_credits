<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'date' => ['nullable', 'date'],
            'type' => ['required', 'in:cash,credit'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'payment' => ['nullable', 'array'],
            'payment.amount' => ['nullable', 'numeric', 'min:0'],
            'payment.method' => ['nullable', 'in:cash,bank,cheque,card,other'],
            'payment.reference' => ['nullable', 'string', 'max:80'],
            'installments' => ['nullable', 'array'],
            'installments.count' => ['nullable', 'integer', 'min:1', 'max:36'],
            'installments.first_due_date' => ['nullable', 'date'],
            'installments.interval_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('type') === 'credit' && ! $this->input('client_id')) {
                $validator->errors()->add('client_id', __('Un client est obligatoire pour une vente à crédit.'));
            }
        });
    }
}
