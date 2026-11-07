<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CreditResource;
use App\Models\Credit;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 20)));

        $query = Credit::query()
            ->with(['client', 'sale'])
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->boolean('overdue'), fn ($q) => $q->overdue())
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = '%'.$request->string('search')->toString().'%';
                $q->whereHas('client', fn ($c) => $c->where('name', 'like', $s)->orWhere('phone', 'like', $s)->orWhere('code', 'like', $s));
            })
            ->when($request->filled('from'), fn ($q) => $q->whereDate('start_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('start_date', '<=', $request->date('to')))
            ->latest('start_date')
            ->latest('id');

        return CreditResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function show(Credit $credit)
    {
        $credit->load(['client', 'sale.items.product', 'installments', 'payments.receiver']);

        return response()->json([
            'data' => new CreditResource($credit),
            'payments' => \App\Http\Resources\PaymentResource::collection($credit->payments),
        ]);
    }
}
