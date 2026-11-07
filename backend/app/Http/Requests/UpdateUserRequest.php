<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        $id = $this->route('user')?->id;

        return [
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username'.($id ? ",$id" : '')],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email'.($id ? ",$id" : '')],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', 'in:admin,manager,cashier,accountant'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('Le nom est obligatoire.'),
            'username.required' => __('L\'identifiant est obligatoire.'),
            'username.unique' => __('Cet identifiant est déjà utilisé.'),
            'username.alpha_dash' => __('L\'identifiant ne doit contenir que des lettres, chiffres, tirets et underscores.'),
            'email.unique' => __('Cet email est déjà utilisé.'),
            'role.required' => __('Le rôle est obligatoire.'),
        ];
    }
}
