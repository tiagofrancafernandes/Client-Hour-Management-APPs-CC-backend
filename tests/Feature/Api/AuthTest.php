<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();
    }

    private function seedPermissions(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'client.view',
            'client.view_any',
            'client.create',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions($permissions);
    }

    public function testUserCanLoginWithValidCredentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);
        $user->assignRole('admin');

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'user' => ['id', 'name', 'email'],
            'role',
            'permissions',
            'token',
        ]);
        $response->assertJsonPath('role', 'admin');
    }

    public function testUserCannotLoginWithInvalidCredentials(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422);
    }

    public function testLoginReturnsUserPermissions(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('admin');

        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('permissions', [
            'client.view',
            'client.view_any',
            'client.create',
        ]);
    }

    public function testUserCanLogout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout');

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Logged out successfully');
    }

    public function testValidateTokenReturnsUserInfo(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $token = $user->createToken('auth-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/validate');

        $response->assertStatus(200);
        $response->assertJsonPath('valid', true);
        $response->assertJsonStructure([
            'valid',
            'user' => ['id', 'name', 'email'],
            'role',
            'permissions',
        ]);
    }

    public function testValidateTokenFailsWithoutToken(): void
    {
        $response = $this->getJson('/api/auth/validate');

        $response->assertStatus(401);
    }

    public function testMeEndpointReturnsCurrentUser(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $token = $user->createToken('auth-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me');

        $response->assertStatus(200);
        $response->assertJsonPath('user.id', $user->id);
        $response->assertJsonPath('role', 'admin');
    }
}
