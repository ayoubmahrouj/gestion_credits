<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(Request $request)
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 20)));

        $query = Payment::query()
            ->with(['client', 'receiver'])
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('method'), fn ($q) => $q->where('method', $request->string('method')->toString()))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('paid_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('paid_at', '<=', $request->date('to')))
            ->latest('paid_at')
            ->latest('id');

        return PaymentResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function store(StorePaymentRequest $request)
    {
        $data = $request->validated();
        $data['idempotency_key'] ??= $request->header('Idempotency-Key');

        $payment = $this->payments->register($data, $request->user());

        return (new PaymentResource($payment->load(['client', 'receiver', 'allocations.installment'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Payment $payment)
    {
        $payment->load(['client', 'receiver', 'credit.sale', 'credit.installments', 'allocations.installment']);

        return new PaymentResource($payment);
    }
}
