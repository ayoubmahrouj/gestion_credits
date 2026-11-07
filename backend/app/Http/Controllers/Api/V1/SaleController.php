<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function __construct(private readonly SaleService $sales) {}

    public function index(Request $request)
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 20)));

        $query = Sale::query()
            ->with(['client', 'user'])
            ->withCount('items')
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('date', '<=', $request->date('to')))
            ->when($request->filled('search'), fn ($q) => $q->where('number', 'like', '%'.$request->string('search')->toString().'%'))
            ->latest('date')
            ->latest('id');

        return SaleResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function store(StoreSaleRequest $request)
    {
        $sale = $this->sales->create($request->validated(), $request->user());

        return (new SaleResource($sale))->response()->setStatusCode(201);
    }

    public function show(Sale $sale)
    {
        $sale->load(['client', 'user', 'items.product', 'credit.installments']);

        return new SaleResource($sale);
    }
}
