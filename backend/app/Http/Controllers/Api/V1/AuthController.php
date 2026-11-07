<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'string', 'max:100'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:80'],
        ]);

        $identifier = trim((string) ($data['login'] ?? $data['email'] ?? ''));

        if ($identifier === '') {
            throw ValidationException::withMessages([
                'login' => __('Identifiant requis.'),
            ]);
        }

        /** @var User|null $user */
        $user = User::query()
            ->where('username', $identifier)
            ->orWhere('email', $identifier)
            ->orWhereRaw('lower(username) = ?', [mb_strtolower($identifier)])
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => __('Identifiants invalides.'),
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'login' => __('Ce compte est désactivé.'),
            ]);
        }

        $token = $user->createToken($data['device_name'] ?? 'web')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    public function me(Request $request)
    {
        return new UserResource($request->user());
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed', 'different:current_password'],
        ], [
            'password.different' => __('Le nouveau mot de passe doit être différent du mot de passe actuel.'),
            'password.confirmed' => __('La confirmation du mot de passe ne correspond pas.'),
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('Le mot de passe actuel est incorrect.'),
            ]);
        }

        $user->update(['password' => $data['password']]);

        $current = $user->currentAccessToken();

        if ($current) {
            $user->tokens()->where('id', '!=', $current->id)->delete();
        }

        return response()->json(['message' => __('Mot de passe modifié.')]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => __('Déconnecté avec succès.')]);
    }
}
