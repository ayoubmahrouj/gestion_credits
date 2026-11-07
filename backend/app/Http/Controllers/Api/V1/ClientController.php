<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientRequest;
use App\Http\Resources\ClientResource;
use App\Http\Resources\CreditResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\SaleResource;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 20)));
        [$column, $direction] = $this->sort($request);

        $query = Client::query()
            ->withCount(['credits', 'sales'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = '%'.$request->string('search')->toString().'%';
                $q->where(fn ($w) => $w->where('name', 'like', $s)
                    ->orWhere('code', 'like', $s)
                    ->orWhere('phone', 'like', $s)
                    ->orWhere('ice', 'like', $s));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when(! $request->boolean('with_disabled'), fn ($q) => $q->where('is_active', true))
            ->when($request->boolean('only_disabled'), fn ($q) => $q->where('is_active', false))
            ->when($request->boolean('overdue'), fn ($q) => $q->whereHas('credits', fn ($c) => $c->where('status', 'overdue')))
            ->orderBy($column, $direction);

        return ClientResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function store(StoreClientRequest $request)
    {
        $data = $request->validated();
        $data['code'] = $this->nextCode();
        $data['score'] = 50;
        $data['balance'] = (float) $request->input('balance', 0);
        $data['is_active'] = true;

        $client = Client::create($data);

        return (new ClientResource($client))->response()->setStatusCode(201);
    }

    public function show(Client $client)
    {
        $client->loadCount(['credits', 'sales'])
            ->load([
                'credits' => fn ($q) => $q->with(['installments', 'sale'])->latest()->limit(20),
                'payments' => fn ($q) => $q->latest('paid_at')->limit(20),
            ]);

        return response()->json([
            'data' => new ClientResource($client),
            'summary' => [
                'credit_total' => round((float) $client->credits()->sum('balance'), 2),
                'paid_total' => round((float) $client->payments()->sum('amount'), 2),
                'overdue_total' => round((float) $client->credits()->where('status', 'overdue')->sum('balance'), 2),
            ],
            'credits' => CreditResource::collection($client->credits),
            'payments' => PaymentResource::collection($client->payments),
        ]);
    }

    public function update(StoreClientRequest $request, Client $client)
    {
        $client->update($request->validated());

        return new ClientResource($client->loadCount(['credits', 'sales']));
    }

    public function destroy(Request $request, Client $client)
    {
        if ((float) $client->balance > 0) {
            throw ValidationException::withMessages([
                'client' => __('Impossible de désactiver : ce client a un solde impayé de :amount DH.', [
                    'amount' => number_format((float) $client->balance, 2, ',', ' '),
                ]),
            ]);
        }

        $client->update(['is_active' => false]);

        return response()->json(['message' => __('Client désactivé (les données sont conservées).')]);
    }

    public function restore(Client $client)
    {
        $client->update(['is_active' => true]);

        return new ClientResource($client->loadCount(['credits', 'sales']));
    }

    /**
     * Relevé de compte client sur une période.
     */
    public function statement(Request $request, Client $client)
    {
        $from = Carbon::parse($request->input('from', now()->startOfYear()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('to', now()->toDateString()))->endOfDay();

        $sales = $client->sales()->with('items.product')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get();

        $payments = $client->payments()->with('receiver')
            ->whereBetween('paid_at', [$from, $to])
            ->orderBy('paid_at')
            ->get();

        $credits = $client->credits()->with('installments')->latest()->get();

        return response()->json([
            'client' => new ClientResource($client),
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'sales' => SaleResource::collection($sales),
            'payments' => PaymentResource::collection($payments),
            'credits' => CreditResource::collection($credits),
            'totals' => [
                'sales' => round((float) $sales->sum('total'), 2),
                'paid' => round((float) $payments->sum('amount'), 2),
                'balance' => (float) $client->balance,
            ],
        ]);
    }

    private function sort(Request $request): array
    {
        $sort = $request->string('sort', '-created_at')->toString();
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $allowed = ['name', 'code', 'balance', 'score', 'created_at', 'credit_limit'];

        return in_array($column, $allowed, true) ? [$column, $direction] : ['created_at', 'desc'];
    }

    private function nextCode(): string
    {
        $next = ((int) Client::withTrashed()->max('id')) + 1;

        return 'CL-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
