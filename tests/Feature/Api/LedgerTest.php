<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Wallet;
use App\Services\BalanceCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LedgerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    private User $viewer;

    private Client $client;

    private Wallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->operator = User::factory()->create();
        $this->operator->assignRole('operator');

        $this->viewer = User::factory()->create();
        $this->viewer->assignRole('viewer');

        $this->client = Client::factory()->create();
        $this->wallet = Wallet::factory()->create(['client_id' => $this->client->id]);
    }

    private function seedPermissions(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'ledger.view',
            'ledger.view_any',
            'ledger.credit',
            'ledger.debit',
            'ledger.adjust',
            'wallet.view',
            'wallet.view_any',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions($permissions);

        $operator = Role::firstOrCreate(['name' => 'operator']);
        $operator->syncPermissions(['ledger.view', 'ledger.view_any', 'ledger.debit', 'wallet.view', 'wallet.view_any']);

        $viewer = Role::firstOrCreate(['name' => 'viewer']);
        $viewer->syncPermissions(['ledger.view', 'ledger.view_any', 'wallet.view', 'wallet.view_any']);
    }

    public function testBalanceIsCalculatedFromSumOfEntries(): void
    {
        LedgerEntry::factory()->create([
            'wallet_id' => $this->wallet->id,
            'hours' => 10,
        ]);

        LedgerEntry::factory()->create([
            'wallet_id' => $this->wallet->id,
            'hours' => -3,
        ]);

        LedgerEntry::factory()->create([
            'wallet_id' => $this->wallet->id,
            'hours' => 5,
        ]);

        $calculator = app(BalanceCalculatorService::class);
        $balance = $calculator->getWalletBalance($this->wallet);

        $this->assertEquals('12.00', $balance);
    }

    public function testBalanceNeverStoredAlwaysCalculated(): void
    {
        $this->assertFalse(
            in_array('balance', $this->wallet->getFillable()),
            'Wallet should not have a balance column'
        );
    }

    public function testAdminCanAddCredit(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/ledger-entries', [
                'wallet_id' => $this->wallet->id,
                'type' => 'credit',
                'hours' => 10,
                'title' => 'Test credit',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('entry.hours', '10.00');
    }

    public function testAdminCanAddDebit(): void
    {
        LedgerEntry::factory()->create([
            'wallet_id' => $this->wallet->id,
            'hours' => 10,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/ledger-entries', [
                'wallet_id' => $this->wallet->id,
                'type' => 'debit',
                'hours' => 3,
                'title' => 'Test debit',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('entry.hours', '-3.00');
        $response->assertJsonPath('new_balance', '7.00');
    }

    public function testAdminCanAddAdjustment(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/ledger-entries', [
                'wallet_id' => $this->wallet->id,
                'type' => 'adjustment',
                'hours' => -5,
                'title' => 'Test adjustment',
            ]);

        $response->assertStatus(201);
    }

    public function testOperatorCanAddDebit(): void
    {
        LedgerEntry::factory()->create([
            'wallet_id' => $this->wallet->id,
            'hours' => 10,
        ]);

        $response = $this->actingAs($this->operator)
            ->postJson('/api/ledger-entries', [
                'wallet_id' => $this->wallet->id,
                'type' => 'debit',
                'hours' => 2,
                'title' => 'Operator debit',
            ]);

        $response->assertStatus(201);
    }

    public function testOperatorCannotAddCredit(): void
    {
        $response = $this->actingAs($this->operator)
            ->postJson('/api/ledger-entries', [
                'wallet_id' => $this->wallet->id,
                'type' => 'credit',
                'hours' => 10,
                'title' => 'Unauthorized credit',
            ]);

        $response->assertStatus(403);
    }

    public function testOperatorCannotAddAdjustment(): void
    {
        $response = $this->actingAs($this->operator)
            ->postJson('/api/ledger-entries', [
                'wallet_id' => $this->wallet->id,
                'type' => 'adjustment',
                'hours' => 5,
                'title' => 'Unauthorized adjustment',
            ]);

        $response->assertStatus(403);
    }

    public function testViewerCannotAddAnyEntry(): void
    {
        $response = $this->actingAs($this->viewer)
            ->postJson('/api/ledger-entries', [
                'wallet_id' => $this->wallet->id,
                'type' => 'debit',
                'hours' => 1,
                'title' => 'Unauthorized entry',
            ]);

        $response->assertStatus(403);
    }

    public function testLedgerEntriesAreImmutable(): void
    {
        $entry = LedgerEntry::factory()->create([
            'wallet_id' => $this->wallet->id,
            'hours' => 10,
        ]);

        $this->assertDatabaseHas('ledger_entries', [
            'id' => $entry->id,
            'hours' => '10.00',
        ]);

        // No update/delete endpoints should exist
        $response = $this->actingAs($this->admin)
            ->putJson("/api/ledger-entries/{$entry->id}", [
                'hours' => 20,
            ]);

        $response->assertStatus(405); // Method not allowed
    }

    public function testNegativeBalanceIsAllowed(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/ledger-entries', [
                'wallet_id' => $this->wallet->id,
                'type' => 'debit',
                'hours' => 100,
                'title' => 'Large debit',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('new_balance', '-100.00');
    }

    public function testWalletBalanceEndpointReturnsCalculatedBalance(): void
    {
        LedgerEntry::factory()->create([
            'wallet_id' => $this->wallet->id,
            'hours' => 25,
        ]);

        LedgerEntry::factory()->create([
            'wallet_id' => $this->wallet->id,
            'hours' => -10,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/wallets/{$this->wallet->id}/balance");

        $response->assertStatus(200);
        $response->assertJsonPath('balance', '15.00');
    }
}
