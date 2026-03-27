<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $availableRoles = Role::pluck('name')->toArray();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', 'in:' . implode(',', $availableRoles)],
            'customer_id' => ['nullable', 'integer', 'exists:clients,id'],
            'client_role' => ['nullable', 'string', 'in:admin,member'],
        ]);

        $userData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ];

        if (!empty($validated['customer_id'])) {
            $client = Client::find($validated['customer_id']);

            $hasAdminAlready = $client->users()->where('client_role', 'admin')->exists();

            $userData['customer_id'] = $validated['customer_id'];
            $userData['client_role'] = $validated['client_role'] ?? ($hasAdminAlready ? 'member' : 'admin');
        }

        $user = User::create($userData);

        $user->assignRole($validated['role']);

        return response()->json([
            'user' => $user->load(['roles', 'client']),
            'message' => 'User created successfully',
        ], 201);
    }

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

        if ($request->boolean('without_client')) {
            $query->whereNull('customer_id');
        }

        if ($request->input('role')) {
            $query->role($request->input('role'));
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
