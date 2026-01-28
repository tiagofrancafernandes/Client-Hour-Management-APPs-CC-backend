<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $admin;

    private User $viewer;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('super_admin');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->viewer = User::factory()->create();
        $this->viewer->assignRole('viewer');

        $this->customer = User::factory()->create();
        $this->customer->assignRole('customer');
    }

    private function seedPermissions(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'client.view',
            'client.view_any',
            'client.create',
            'client.update',
            'client.delete',
            'wallet.view',
            'wallet.view_any',
            'wallet.create',
            'wallet.update',
            'wallet.delete',
            'tag.view',
            'tag.view_any',
            'tag.create',
            'tag.update',
            'tag.delete',
            'report.view',
            'report.view_any',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdmin->syncPermissions(Permission::all());

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions($permissions);

        $viewer = Role::firstOrCreate(['name' => 'viewer']);
        $viewer->syncPermissions([
            'client.view',
            'client.view_any',
            'wallet.view',
            'wallet.view_any',
            'tag.view',
            'tag.view_any',
            'report.view',
        ]);

        $customer = Role::firstOrCreate(['name' => 'customer']);
        $customer->syncPermissions([
            'client.view',
            'wallet.view',
            'tag.view',
            'report.view',
        ]);
    }

    // Client Permission Tests

    public function testAdminCanViewClients(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/clients');

        $response->assertStatus(200);
    }

    public function testAdminCanCreateClient(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/clients', [
                'name' => 'Test Client',
            ]);

        $response->assertStatus(201);
    }

    public function testViewerCanViewClients(): void
    {
        $response = $this->actingAs($this->viewer)
            ->getJson('/api/clients');

        $response->assertStatus(200);
    }

    public function testViewerCannotCreateClient(): void
    {
        $response = $this->actingAs($this->viewer)
            ->postJson('/api/clients', [
                'name' => 'Test Client',
            ]);

        $response->assertStatus(403);
    }

    public function testViewerCannotDeleteClient(): void
    {
        $client = Client::factory()->create();

        $response = $this->actingAs($this->viewer)
            ->deleteJson("/api/clients/{$client->id}");

        $response->assertStatus(403);
    }

    // Wallet Permission Tests

    public function testAdminCanCreateWallet(): void
    {
        $client = Client::factory()->create();

        $response = $this->actingAs($this->admin)
            ->postJson('/api/wallets', [
                'client_id' => $client->id,
                'name' => 'Test Wallet',
            ]);

        $response->assertStatus(201);
    }

    public function testViewerCannotCreateWallet(): void
    {
        $client = Client::factory()->create();

        $response = $this->actingAs($this->viewer)
            ->postJson('/api/wallets', [
                'client_id' => $client->id,
                'name' => 'Test Wallet',
            ]);

        $response->assertStatus(403);
    }

    // Tag Permission Tests

    public function testAdminCanCreateTag(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/tags', [
                'name' => 'Test Tag',
            ]);

        $response->assertStatus(201);
    }

    public function testViewerCannotCreateTag(): void
    {
        $response = $this->actingAs($this->viewer)
            ->postJson('/api/tags', [
                'name' => 'Test Tag',
            ]);

        $response->assertStatus(403);
    }

    public function testViewerCannotDeleteTag(): void
    {
        $tag = Tag::factory()->create();

        $response = $this->actingAs($this->viewer)
            ->deleteJson("/api/tags/{$tag->id}");

        $response->assertStatus(403);
    }

    // Report Permission Tests

    public function testAdminCanViewReports(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/reports');

        $response->assertStatus(200);
    }

    public function testViewerCanViewReports(): void
    {
        $response = $this->actingAs($this->viewer)
            ->getJson('/api/reports');

        $response->assertStatus(200);
    }

    public function testAdminCanExportReportsToExcel(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/api/reports/export?format=excel');

        $response->assertSuccessful();
    }

    public function testAdminCanExportReportsToPdf(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/api/reports/export?format=pdf');

        $response->assertSuccessful();
    }

    public function testViewerCanExportReports(): void
    {
        $response = $this->actingAs($this->viewer)
            ->get('/api/reports/export?format=excel');

        $response->assertSuccessful();
    }

    public function testUnauthenticatedCannotExportReports(): void
    {
        $response = $this->get('/api/reports/export?format=excel');

        $response->assertUnauthorized();
    }

    // Unauthenticated Tests

    public function testUnauthenticatedCannotAccessClients(): void
    {
        $response = $this->getJson('/api/clients');

        $response->assertStatus(401);
    }

    public function testUnauthenticatedCannotAccessWallets(): void
    {
        $response = $this->getJson('/api/wallets');

        $response->assertStatus(401);
    }

    public function testUnauthenticatedCannotAccessReports(): void
    {
        $response = $this->getJson('/api/reports');

        $response->assertStatus(401);
    }
}
