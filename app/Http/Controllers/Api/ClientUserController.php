<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientUserRequest;
use App\Http\Requests\UpdateClientUserRequest;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ClientUserController extends Controller
{
    /**
     * Obter usuários de um cliente
     * GET /api/clients/{client}/users
     */
    public function index(Client $client): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $users = $client->users()->with('roles')->get();

        return response()->json($users);
    }

    /**
     * Criar novo usuário e associá-lo ao cliente
     * POST /api/clients/{client}/users
     */
    public function store(StoreClientUserRequest $request, Client $client): JsonResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        $data['customer_id'] = $client->id;

        $hasAdminAlready = $client->users()->where('client_role', 'admin')->exists();
        $data['client_role'] = $hasAdminAlready ? 'member' : 'admin';

        $user = User::create($data);

        $user->assignRole('customer');

        return response()->json([
            'user' => $user->load('roles'),
            'message' => 'Usuário criado com sucesso',
        ], 201);
    }

    /**
     * Vincular usuário existente a um cliente
     * POST /api/clients/{client}/users/attach
     */
    public function attach(Request $request, Client $client): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['nullable', 'string', 'in:admin,member'],
        ]);

        $user = User::findOrFail($validated['user_id']);

        if ($user->customer_id && $user->customer_id !== $client->id) {
            return response()->json([
                'message' => 'Usuário já pertence a outro cliente',
            ], 422);
        }

        $hasAdminAlready = $client->users()->where('client_role', 'admin')->exists();
        $role = $validated['role'] ?? ($hasAdminAlready ? 'member' : 'admin');

        if ($role === 'admin') {
            $client->users()->where('client_role', 'admin')->update(['client_role' => 'member']);
        }

        $user->customer_id = $client->id;
        $user->client_role = $role;
        $user->save();

        if (!$user->hasRole('customer')) {
            $user->assignRole('customer');
        }

        return response()->json([
            'user' => $user->fresh()->load('roles'),
            'message' => 'Usuário vinculado com sucesso',
        ]);
    }

    /**
     * Definir um usuário como administrador do cliente
     * PUT /api/clients/{client}/users/{user}/set-admin
     */
    public function setAdmin(Client $client, User $user): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        if ($user->customer_id !== $client->id) {
            return response()->json([
                'message' => 'Usuário não pertence a este cliente',
            ], 403);
        }

        $client->users()->where('client_role', 'admin')->update(['client_role' => 'member']);

        $user->client_role = 'admin';
        $user->save();

        return response()->json([
            'user' => $user->fresh()->load('roles'),
            'message' => 'Admin atualizado com sucesso',
        ]);
    }

    /**
     * Atualizar dados de usuário do cliente
     * PUT /api/clients/{client}/users/{user}
     */
    public function update(UpdateClientUserRequest $request, Client $client, User $user): JsonResponse
    {
        if ($user->customer_id !== $client->id) {
            return response()->json([
                'message' => 'Usuário não pertence a este cliente',
            ], 403);
        }

        $data = $request->validated();

        if (isset($data['password']) && $data['password']) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return response()->json([
            'user' => $user->fresh()->load('roles'),
            'message' => 'Usuário atualizado com sucesso',
        ]);
    }

    /**
     * Deletar usuário de cliente
     * DELETE /api/clients/{client}/users/{user}
     */
    public function destroy(Client $client, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        if ($user->customer_id !== $client->id) {
            return response()->json([
                'message' => 'Usuário não pertence a este cliente',
            ], 403);
        }

        if ($user->hasRole(['super_admin', 'admin'])) {
            $adminCount = User::role(['super_admin', 'admin'])->count();

            if ($adminCount <= 1) {
                return response()->json([
                    'message' => 'Não é possível deletar o último administrador',
                ], 422);
            }
        }

        $user->delete();

        return response()->json(null, 204);
    }
}
