<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\ProductService;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customerUser;

    private Client $client;

    private Wallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->client = Client::factory()->create(['name' => 'Acme Corporation']);

        $this->wallet = Wallet::factory()->create([
            'client_id' => $this->client->id,
            'name' => 'Main Wallet',
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->customerUser = User::factory()->create([
            'customer_id' => $this->client->id,
        ]);
        $this->customerUser->assignRole('customer');

        // Create sample data
        LedgerEntry::create([
            'wallet_id' => $this->wallet->id,
            'hours' => 10.5,
            'title' => 'Initial Credit',
            'reference_date' => now()->toDateString(),
        ]);

        LedgerEntry::create([
            'wallet_id' => $this->wallet->id,
            'hours' => -3.0,
            'title' => 'Consulting Session',
            'reference_date' => now()->toDateString(),
        ]);

        ProductService::create([
            'name' => 'Dev Hours',
            'type' => 'service',
            'unit' => 'hour',
            'default_price' => 100.0,
            'is_active' => true,
        ]);

        Invoice::create([
            'client_id' => $this->client->id,
            'invoice_number' => 1,
            'client_invoice_number' => 1,
            'issue_date' => now()->toDateString(),
            'currency' => 'USD',
            'subtotal' => 500.0,
            'tax_amount' => 0.0,
            'discount_amount' => 0.0,
            'total' => 500.0,
            'status' => 'sent',
        ]);
    }

    private function seedPermissions(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'client.view',
            'client.view_any',
            'wallet.view',
            'wallet.view_any',
            'ledger.view',
            'ledger.view_any',
            'dashboard.view',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->syncPermissions(Permission::all());

        $customerRole = Role::firstOrCreate(['name' => 'customer']);
        $customerRole->syncPermissions(['client.view', 'wallet.view', 'ledger.view']);
    }

    public function testAdminCanGetDashboardStats(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/dashboard/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'clients' => ['total', 'this_month'],
                    'wallets' => ['total', 'this_month'],
                    'ledger' => ['total_entries', 'entries_this_month', 'net_hours', 'credit_hours', 'debit_hours', 'hours_this_month'],
                    'invoices' => ['total', 'this_month', 'total_amount', 'by_status'],
                    'products_services' => ['total', 'active'],
                    'timers' => ['active_count'],
                    'cached_at',
                ],
                'message',
            ]);

        $this->assertEquals(1, $response->json('data.clients.total'));
        $this->assertEquals(2, $response->json('data.ledger.total_entries'));
        $this->assertEquals(7.5, $response->json('data.ledger.net_hours'));
        $this->assertEquals(1, $response->json('data.invoices.total'));
    }

    public function testCustomerCannotAccessDashboard(): void
    {
        $response = $this->actingAs($this->customerUser, 'sanctum')
            ->getJson('/api/dashboard/stats');

        $response->assertStatus(403);
    }

    public function testAdminCanGetRecentEntriesWithSorting(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/dashboard/recent-entries?order_by=hours&order_direction=desc&limit=5');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'hours',
                        'title',
                        'reference_date',
                        'wallet' => ['id', 'name'],
                        'client' => ['id', 'name'],
                        'tags',
                    ],
                ],
                'message',
            ]);

        $entries = $response->json('data');
        $this->assertCount(2, $entries);
        $this->assertEquals(10.5, $entries[0]['hours']);
    }

    public function testAdminCanGetRecentClients(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/dashboard/recent-clients?limit=5');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'wallets_count',
                        'created_at',
                    ],
                ],
                'message',
            ]);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Acme Corporation', $response->json('data.0.name'));
        $this->assertEquals(1, $response->json('data.0.wallets_count'));
    }

    public function testAdminCanGetRecentInvoices(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/dashboard/recent-invoices?limit=5');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'formatted_code',
                        'client' => ['id', 'name'],
                        'status',
                        'total',
                    ],
                ],
                'message',
            ]);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('sent', $response->json('data.0.status'));
    }

    public function testCacheRefreshWorks(): void
    {
        // First call caches the stats
        $response1 = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/dashboard/stats');
        $response1->assertStatus(200);

        // Add a new client
        Client::factory()->create(['name' => 'New Client']);

        // Call without refresh: count should still be 1 from cache
        $cachedResponse = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/dashboard/stats');
        $this->assertEquals(1, $cachedResponse->json('data.clients.total'));

        // Call with refresh=true: cache is bypassed/refreshed, count should now be 2
        $refreshedResponse = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/dashboard/stats?refresh=true');
        $this->assertEquals(2, $refreshedResponse->json('data.clients.total'));
    }

    public function testClearCacheEndpoint(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/dashboard/clear-cache');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Dashboard cache cleared successfully.']);
    }
}
