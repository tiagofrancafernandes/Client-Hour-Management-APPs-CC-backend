<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use App\Models\LedgerEntry;
use App\Models\Tag;
use App\Models\User;
use App\Services\ReportExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;
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
            'wallet.view_internal_note',
            'wallet.create',
            'wallet.update',
            'wallet.update_rules',
            'wallet.delete',
            'ledger.view',
            'ledger.view_any',
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
            'ledger.view',
            'wallet.view',
            'tag.view',
            'report.view',
        ]);
    }

    private function mockReportExportService(): void
    {
        $mock = $this->mock(ReportExportService::class);

        $response = new StreamedResponse(function () {
            echo 'Excel content';
        });

        $response->headers->set('Content-Disposition', 'attachment; filename="test-mock-report.xlsx"');
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $mock->shouldReceive('exportToExcel')
            ->andReturn($response);

        $mock->shouldReceive('exportToPdf')
            ->andReturn($response);
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

    public function testCustomerCanOnlyListOwnClients(): void
    {
        $customerClient = Client::factory()->create();
        $otherClient = Client::factory()->create();

        $this->customer->forceFill([
            'customer_id' => $customerClient->id,
        ])->save();

        $customerClient->wallets()->create([
            'name' => 'Customer Wallet',
        ]);

        $otherClient->wallets()->create([
            'name' => 'Other Wallet',
        ]);

        $response = $this->actingAs($this->customer)
            ->getJson('/api/clients');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $customerClient->id);
        $response->assertJsonMissingPath('data.1');
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

    public function testAdminCanListAllWalletsAndFilterByClient(): void
    {
        $firstClient = Client::factory()->create();
        $secondClient = Client::factory()->create();

        $firstWallet = $firstClient->wallets()->create([
            'name' => 'First Wallet',
        ]);

        $secondClient->wallets()->create([
            'name' => 'Second Wallet',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/wallets');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment([
            'id' => $firstWallet->id,
        ]);

        $filteredResponse = $this->actingAs($this->admin)
            ->getJson('/api/wallets?client_id=' . $firstClient->id);

        $filteredResponse->assertOk();
        $filteredResponse->assertJsonCount(1, 'data');
        $filteredResponse->assertJsonPath('data.0.id', $firstWallet->id);
    }

    public function testCustomerCanOnlyListOwnWallets(): void
    {
        $customerClient = Client::factory()->create();
        $otherClient = Client::factory()->create();

        $this->customer->forceFill([
            'customer_id' => $customerClient->id,
        ])->save();

        $ownWallet = $customerClient->wallets()->create([
            'name' => 'Customer Wallet',
        ]);

        $otherWallet = $otherClient->wallets()->create([
            'name' => 'Other Wallet',
        ]);

        $response = $this->actingAs($this->customer)
            ->getJson('/api/wallets');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $ownWallet->id);
        $response->assertJsonPath('data.0.client_id', $customerClient->id);
        $response->assertJsonMissingPath('data.1');
        $response->assertJsonMissing(['id' => $otherWallet->id]);
    }

    public function testCustomerCanOnlyListOwnLedgerEntries(): void
    {
        $customerClient = Client::factory()->create();
        $otherClient = Client::factory()->create();

        $this->customer->forceFill([
            'customer_id' => $customerClient->id,
        ])->save();

        $customerWallet = $customerClient->wallets()->create([
            'name' => 'Customer Wallet',
        ]);

        $otherWallet = $otherClient->wallets()->create([
            'name' => 'Other Wallet',
        ]);

        $customerEntry = LedgerEntry::factory()->create([
            'wallet_id' => $customerWallet->id,
        ]);

        LedgerEntry::factory()->create([
            'wallet_id' => $otherWallet->id,
        ]);

        $response = $this->actingAs($this->customer)
            ->getJson('/api/ledger-entries');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $customerEntry->id);
        $response->assertJsonPath('data.0.wallet_id', $customerWallet->id);
        $response->assertJsonMissingPath('data.1');
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

    public function testCustomerCanListTagsWithoutViewAnyPermission(): void
    {
        Tag::factory()->create([
            'name' => 'Development',
        ]);

        Tag::factory()->create([
            'name' => 'Bug Fix',
        ]);

        $response = $this->actingAs($this->customer)
            ->getJson('/api/tags');

        $response->assertOk();
        $response->assertJsonCount(2);
        $response->assertJsonFragment([
            'name' => 'Development',
        ]);
        $response->assertJsonFragment([
            'name' => 'Bug Fix',
        ]);
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
        $this->mockReportExportService();

        $filename = 'test-mock-report.xlsx';

        $response = $this->actingAs($this->admin)
            ->get('/api/reports/export?format=excel&filename=' . $filename);

        $response->assertOk()
                ->assertDownload($filename);
    }

    public function testAdminCanExportReportsToPdf(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/api/reports/export?format=pdf');

        $response->assertSuccessful();
    }

    public function testViewerCanExportReports(): void
    {
        $this->mockReportExportService();

        $filename = 'test-mock-report.xlsx';

        $response = $this->actingAs($this->viewer)
            ->get('/api/reports/export?format=excel&filename=' . $filename);

        $response->assertSuccessful();
    }

    public function testUnauthenticatedCannotExportReports(): void
    {
        $filename = 'test-mock-report.xlsx';

        $response = $this->getJson('/api/reports/export?format=excel&filename=' . $filename);

        $response->assertUnauthorized();
    }

    /* Unauthenticated Tests */

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
