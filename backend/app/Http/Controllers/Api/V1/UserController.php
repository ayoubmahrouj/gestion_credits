<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $this->guardAdmin($request);

        $query = User::query()
            ->when(! $request->boolean('with_disabled'), fn ($q) => $q->where('is_active', true))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = '%'.$request->string('search')->toString().'%';
                $q->where(fn ($w) => $w->where('name', 'like', $s)
                    ->orWhere('username', 'like', $s)
                    ->orWhere('email', 'like', $s)
                    ->orWhere('phone', 'like', $s));
            })
            ->orderByDesc('is_active')
            ->orderBy('name');

        return UserResource::collection($query->get());
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();
        $data['email'] = ($data['email'] ?? null) ?: null;
        $data['is_active'] = $request->boolean('is_active', true);

        $user = User::create($data);

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();
        $data['email'] = ($data['email'] ?? null) ?: null;
        $data['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : $user->is_active;

        $this->guardLastAdmin($user, $data);

        if ($user->id === $request->user()->id && ! $data['is_active']) {
            throw ValidationException::withMessages([
                'is_active' => __('Vous ne pouvez pas désactiver votre propre compte.'),
            ]);
        }

        $user->update($data);

        if (! $user->is_active) {
            $user->tokens()->delete();
        }

        return new UserResource($user->fresh());
    }

    public function destroy(Request $request, User $user)
    {
        $this->guardAdmin($request);

        if ($user->id === $request->user()->id) {
            throw ValidationException::withMessages([
                'user' => __('Vous ne pouvez pas désactiver votre propre compte.'),
            ]);
        }

        $this->guardLastAdmin($user, ['role' => $user->role, 'is_active' => false]);

        $user->update(['is_active' => false]);
        $user->tokens()->delete();

        return response()->json(['message' => __('Utilisateur désactivé.')]);
    }

    public function restore(Request $request, User $user)
    {
        $this->guardAdmin($request);

        $user->update(['is_active' => true]);

        return new UserResource($user->fresh());
    }

    public function resetPassword(Request $request, User $user)
    {
        $this->guardAdmin($request);

        $data = $request->validate([
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user->update(['password' => $data['password']]);

        $current = $request->user()->currentAccessToken();

        if ($user->id === $request->user()->id && $current) {
            $user->tokens()->where('id', '!=', $current->id)->delete();
        } else {
            $user->tokens()->delete();
        }

        return response()->json(['message' => __('Mot de passe réinitialisé.')]);
    }

    private function guardAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, __('Action réservée aux administrateurs.'));
    }

    private function guardLastAdmin(User $user, array $data): void
    {
        $wasActiveAdmin = $user->role === 'admin' && $user->is_active;
        $staysActiveAdmin = ($data['role'] ?? $user->role) === 'admin' && ($data['is_active'] ?? $user->is_active);

        if (! $wasActiveAdmin || $staysActiveAdmin) {
            return;
        }

        $activeAdmins = User::query()->where('role', 'admin')->where('is_active', true)->count();

        if ($activeAdmins <= 1) {
            throw ValidationException::withMessages([
                'role' => __('Impossible : il doit rester au moins un administrateur actif.'),
            ]);
        }
    }
}
