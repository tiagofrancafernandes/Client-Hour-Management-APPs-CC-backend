<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientUserRequest;
use App\Http\Requests\UpdateClientUserRequest;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\JsonResponse;
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
     * Criar usuário para um cliente
     * POST /api/clients/{client}/users
     */
    public function store(StoreClientUserRequest $request, Client $client): JsonResponse
    {
        // Verificar se cliente já tem usuário
        if ($client->users()->exists()) {
            return response()->json([
                'message' => 'Este cliente já possui um usuário associado',
            ], 422);
        }

        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        $data['customer_id'] = $client->id;

        $user = User::create($data);

        // Atribuir role customer
        $user->assignRole('customer');

        return response()->json([
            'user' => $user->load('roles'),
            'message' => 'Usuário criado com sucesso',
        ], 201);
    }

    /**
     * Atualizar usuário de cliente
     * PUT /api/clients/{client}/users/{user}
     */
    public function update(UpdateClientUserRequest $request, Client $client, User $user): JsonResponse
    {
        // Verificar se o usuário pertence a este cliente
        if ($user->customer_id !== $client->id) {
            return response()->json([
                'message' => 'Usuário não pertence a este cliente',
            ], 403);
        }

        $data = $request->validated();

        // Se senha foi fornecida, fazer hash
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

        // Verificar se o usuário pertence a este cliente
        if ($user->customer_id !== $client->id) {
            return response()->json([
                'message' => 'Usuário não pertence a este cliente',
            ], 403);
        }

        // Não permitir deletar se for o último admin/super_admin
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
