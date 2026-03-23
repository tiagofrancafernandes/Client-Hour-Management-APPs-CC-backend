<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('user.view_any');
    }

    public function view(User $authUser, User $user): bool
    {
        // Admin pode ver qualquer usuário
        if ($authUser->can('user.view')) {
            return true;
        }

        // User pode ver a si mesmo
        return $authUser->id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('user.create');
    }

    public function update(User $authUser, User $user): bool
    {
        // Admin pode atualizar qualquer usuário
        if ($authUser->can('user.update')) {
            return true;
        }

        // User pode atualizar a si mesmo
        return $authUser->id === $user->id;
    }

    public function delete(User $authUser, User $user): bool
    {
        // Não pode deletar a si mesmo
        if ($authUser->id === $user->id) {
            return false;
        }

        return $authUser->can('user.delete');
    }

    public function manageClientUser(User $authUser, int $clientId): bool
    {
        if ($authUser->can('user.manage_client_users')) {
            return true;
        }

        // Manager pode gerenciar usuários de clientes que tem acesso
        // (Adicionar lógica específica se necessário)
        return false;
    }
}
