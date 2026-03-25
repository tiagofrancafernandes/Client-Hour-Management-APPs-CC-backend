<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $query = User::with(['roles', 'client'])
            ->orderBy('name');

        if ($request->input('search')) {
            $search = $request->input('search');

            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $users = $query->paginate(20);

        return response()->json($users);
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        $user->load(['roles', 'client']);

        $directPermissions = $user->getDirectPermissions()->pluck('name');
        $rolePermissions = $user->getPermissionsViaRoles()->pluck('name');

        return response()->json([
            'user' => $user,
            'direct_permissions' => $directPermissions,
            'role_permissions' => $rolePermissions,
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ]);

        $user->update($validated);

        return response()->json([
            'user' => $user->fresh(['roles', 'client']),
            'message' => 'User updated successfully',
        ]);
    }

    public function updateRole(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $availableRoles = Role::pluck('name')->toArray();

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:' . implode(',', $availableRoles)],
        ]);

        $user->syncRoles([$validated['role']]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json([
            'user' => $user->fresh(['roles', 'client']),
            'message' => 'Role updated successfully',
        ]);
    }

    public function updatePermissions(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $availablePermissions = Permission::pluck('name')->toArray();

        $validated = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'in:' . implode(',', $availablePermissions)],
        ]);

        $user->syncPermissions($validated['permissions']);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json([
            'direct_permissions' => $user->getDirectPermissions()->pluck('name'),
            'message' => 'Permissions updated successfully',
        ]);
    }

    public function availableOptions(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $roles = Role::orderBy('name')->pluck('name');
        $permissions = Permission::orderBy('name')->pluck('name');

        return response()->json([
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }
}
