<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Reminder;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 20)));

        $query = Reminder::query()
            ->with(['client:id,name,phone,whatsapp', 'credit:id,balance,due_date'])
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->latest('id');

        return response()->json($query->paginate($perPage)->withQueryString());
    }

    /**
     * Enregistre une relance (envoi manuel ou journalisé par un job).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'credit_id' => ['nullable', 'integer', 'exists:credits,id'],
            'channel' => ['nullable', 'in:whatsapp,sms,email,call'],
            'template' => ['nullable', 'string', 'max:50'],
            'message' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'in:pending,sent,failed,answered'],
        ]);

        $reminder = Reminder::create([
            ...$data,
            'sent_at' => ($data['status'] ?? null) === 'sent' ? now() : null,
        ]);

        return response()->json(['data' => $reminder], 201);
    }
}
