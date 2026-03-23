<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Verifica se tem permissão para criar usuários
        if (!$this->user()->can('user.create')) {
            return false;
        }

        // Verifica se tem permissão para gerenciar usuários deste cliente
        $clientId = $this->input('client_id');

        if ($clientId) {
            return $this->user()->can('manageClientUser', [\App\Models\User::class, $clientId]);
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
