<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WalletValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->client = Client::factory()->create();
    }

    private function seedPermissions(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'wallet.view',
            'wallet.view_any',
            'wallet.view_internal_note',
            'wallet.create',
            'wallet.update',
            'wallet.update_rules',
            'wallet.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions($permissions);
    }

    public function testCannotEnableCreditPurchaseWithoutHourlyRate(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/wallets', [
                'client_id' => $this->client->id,
                'name' => 'Test Wallet',
                'credit_purchase_allowed' => true,
                'currency_code' => 'USD',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['hourly_rate_reference']);
    }

    public function testCannotEnableCreditPurchaseWithoutCurrency(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/wallets', [
                'client_id' => $this->client->id,
                'name' => 'Test Wallet',
                'credit_purchase_allowed' => true,
                'hourly_rate_reference' => 100.00,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['currency_code']);
    }

    public function testCanEnableCreditPurchaseWithBothFields(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/wallets', [
                'client_id' => $this->client->id,
                'name' => 'Test Wallet',
                'credit_purchase_allowed' => true,
                'hourly_rate_reference' => 100.00,
                'currency_code' => 'USD',
            ]);

        $response->assertStatus(201);
    }

    public function testCanCreateWalletWithoutCreditPurchaseEnabled(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/wallets', [
                'client_id' => $this->client->id,
                'name' => 'Test Wallet',
                'credit_purchase_allowed' => false,
            ]);

        $response->assertStatus(201);
    }

    public function testCanUpdateWalletToEnableCreditPurchase(): void
    {
        $wallet = $this->client->wallets()->create([
            'name' => 'Test Wallet',
            'credit_purchase_allowed' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/wallets/{$wallet->id}", [
                'credit_purchase_allowed' => true,
                'hourly_rate_reference' => 100.00,
                'currency_code' => 'USD',
            ]);

        $response->assertStatus(200);
    }

    public function testCannotUpdateWalletToEnableCreditPurchaseWithoutRate(): void
    {
        $wallet = $this->client->wallets()->create([
            'name' => 'Test Wallet',
            'credit_purchase_allowed' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/wallets/{$wallet->id}", [
                'credit_purchase_allowed' => true,
                'currency_code' => 'USD',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['hourly_rate_reference']);
    }
}
