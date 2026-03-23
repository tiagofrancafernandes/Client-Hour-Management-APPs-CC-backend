<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateClientUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = User::findOrFail($this->route('user'));

        return $this->user()->can('update', $user);
    }

    public function rules(): array
    {
        $userId = $this->route('user');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email,' . $userId],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'],
            'client_id' => ['sometimes', 'nullable', 'integer', 'exists:clients,id'],
        ];
    }
}
