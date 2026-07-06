<?php

namespace Tests\Feature\Api;

use App\Models\PaymentMethodConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SetupFieldsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->viewer = User::factory()->create();
        $this->viewer->assignRole('viewer');
    }

    private function seedPermissions(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'payment_method.view_any',
            'payment_method.update_any',
            'payment_method.toggle',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions($permissions);

        $viewer = Role::firstOrCreate(['name' => 'viewer']);
        $viewer->syncPermissions([]);
    }

    /**
     * Test setup_fields are persisted as JSON
     */
    public function testSetupFieldsArePersistedAsJson(): void
    {
        $config = PaymentMethodConfig::factory()->create();

        $setupFields = [
            'pix_key' => 'test-pix-key-123',
            'receiver_name' => 'João Silva',
            'receiver_city_name' => 'São Paulo',
        ];

        $response = $this->actingAs($this->admin)
            ->putJson("/api/admin/payment-method-configs/{$config->id}", [
                'setup_fields' => $setupFields,
            ]);

        $response->assertStatus(200);

        $saved = PaymentMethodConfig::find($config->id);

        // Model's json cast returns an array
        $this->assertIsArray($saved->setup_fields);
        $this->assertSame('test-pix-key-123', $saved->setup_fields['pix_key']);
        $this->assertSame('João Silva', $saved->setup_fields['receiver_name']);
    }

    /**
     * Test setup_fields returned in show response
     */
    public function testSetupFieldsReturnedInShowResponse(): void
    {
        $setupFieldsData = [
            'pix_key' => 'key-pix-456',
            'receiver_name' => 'Maria Santos',
            'receiver_city_name' => 'Rio de Janeiro',
        ];

        $config = PaymentMethodConfig::factory()->create([
            'setup_fields' => $setupFieldsData,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/admin/payment-method-configs/{$config->id}");

        $response->assertStatus(200);

        $responseSetupFields = $response->json('setup_fields');
        $this->assertIsArray($responseSetupFields);
        $this->assertSame('key-pix-456', $responseSetupFields['pix_key']);
        $this->assertSame('Maria Santos', $responseSetupFields['receiver_name']);
    }

    /**
     * Test empty setup_fields array becomes null
     */
    public function testEmptySetupFieldsAreNull(): void
    {
        $config = PaymentMethodConfig::factory()->create([
            'setup_fields' => [
                'pix_key' => 'some-key',
                'receiver_name' => 'Some Name',
            ],
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/admin/payment-method-configs/{$config->id}", [
                'setup_fields' => [],
            ]);

        $response->assertStatus(200);

        $saved = PaymentMethodConfig::find($config->id);
        $this->assertNull($saved->setup_fields);
    }

    /**
     * Test setup_fields persists special characters
     */
    public function testSetupFieldsPersistsSpecialCharacters(): void
    {
        $config = PaymentMethodConfig::factory()->create();

        $setupFields = [
            'pix_key' => '123.456.789-10 @ banco.com.br',
            'receiver_name' => 'José da Silva (CPF)',
            'receiver_city_name' => 'São Paulo & Região',
        ];

        $response = $this->actingAs($this->admin)
            ->putJson("/api/admin/payment-method-configs/{$config->id}", [
                'setup_fields' => $setupFields,
            ]);

        $response->assertStatus(200);

        $saved = PaymentMethodConfig::find($config->id);

        // Model's json cast returns an array
        $this->assertStringContainsString('@', $saved->setup_fields['pix_key']);
        $this->assertStringContainsString('CPF', $saved->setup_fields['receiver_name']);
        $this->assertStringContainsString('&', $saved->setup_fields['receiver_city_name']);
    }

    /**
     * Test getSetupFieldRules returns payment method rules
     */
    public function testGetSetupFieldRulesReturnsPaymentMethodRules(): void
    {
        $config = PaymentMethodConfig::factory()->create([
            'payment_method_key' => 'pix_offline',
        ]);

        $rules = $config->getSetupFieldRules();

        $this->assertIsArray($rules);
        $this->assertNotEmpty($rules);

        $ruleNames = array_map(fn ($rule) => $rule['name'], $rules);
        $this->assertContains('pix_key', $ruleNames);
        $this->assertContains('receiver_name', $ruleNames);
        $this->assertContains('receiver_city_name', $ruleNames);

        $pixKeyRule = array_find($rules, fn ($rule) => $rule['name'] === 'pix_key');
        $this->assertTrue($pixKeyRule['required']);
        $this->assertSame('string', $pixKeyRule['type']);
    }

    /**
     * Test getSetupFieldDefaults returns payment method defaults
     */
    public function testGetSetupFieldDefaultsReturnsPaymentMethodDefaults(): void
    {
        $config = PaymentMethodConfig::factory()->create([
            'payment_method_key' => 'pix_offline',
        ]);

        $defaults = $config->getSetupFieldDefaults();

        $this->assertIsArray($defaults);
        $this->assertArrayHasKey('receiver_city_name', $defaults);
        $this->assertSame('São Paulo', $defaults['receiver_city_name']);
    }

    /**
     * Test isSetupComplete returns false when required fields are missing
     */
    public function testIsSetupCompleteReturnsFalseWhenRequiredFieldsMissing(): void
    {
        $config = PaymentMethodConfig::factory()->create([
            'payment_method_key' => 'pix_offline',
            'setup_fields' => json_encode([
                'pix_key' => 'test-key',
                // Missing required fields: receiver_name, receiver_city_name
            ]),
        ]);

        $this->assertFalse($config->isSetupComplete());
    }

    /**
     * Test isSetupComplete returns true when all required fields are filled
     */
    public function testIsSetupCompleteReturnsTrueWhenAllRequiredFieldsFilled(): void
    {
        $config = PaymentMethodConfig::factory()->create([
            'payment_method_key' => 'pix_offline',
            'setup_fields' => [
                'pix_key' => 'test-pix-key-123',
                'receiver_name' => 'João Silva',
                'receiver_city_name' => 'São Paulo',
            ],
        ]);

        $this->assertTrue($config->isSetupComplete());
    }

    /**
     * Test isSetupComplete returns true when no required fields exist
     */
    public function testIsSetupCompleteReturnsTrueWhenNoRequiredFields(): void
    {
        $config = PaymentMethodConfig::factory()->create();

        // This payment method likely has no setup field rules
        $this->assertTrue($config->isSetupComplete());
    }

    /**
     * Test list endpoint includes setup completeness info
     */
    public function testListIncludesSetupCompletenessInfo(): void
    {
        // Create one with complete setup
        PaymentMethodConfig::factory()->create([
            'payment_method_key' => 'pix_offline_complete_' . uniqid(),
            'setup_fields' => [
                'pix_key' => 'test-pix-key-123',
                'receiver_name' => 'João Silva',
                'receiver_city_name' => 'São Paulo',
            ],
        ]);

        // Create one with incomplete setup (using different payment method)
        PaymentMethodConfig::factory()->create([
            'payment_method_key' => 'bank_transfer_incomplete_' . uniqid(),
            'setup_fields' => [
                'partial_field' => 'incomplete-data',
            ],
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/payment-method-configs');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(2, count($data));

        // Verify both items have is_setup_complete key
        foreach ($data as $item) {
            $this->assertArrayHasKey('is_setup_complete', $item);
            $this->assertIsBool($item['is_setup_complete']);
        }
    }

    /**
     * Test non-admin cannot access setup fields endpoints
     */
    public function testNonAdminCannotAccess(): void
    {
        $config = PaymentMethodConfig::factory()->create();

        $response = $this->actingAs($this->viewer)
            ->putJson("/api/admin/payment-method-configs/{$config->id}", [
                'setup_fields' => ['test' => 'data'],
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test unauthenticated user cannot access setup fields endpoints
     */
    public function testUnauthenticatedCannotAccess(): void
    {
        $config = PaymentMethodConfig::factory()->create();

        $response = $this->putJson("/api/admin/payment-method-configs/{$config->id}", [
            'setup_fields' => ['test' => 'data'],
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test setup_fields with numeric and boolean values
     */
    public function testSetupFieldsWithNumericAndBooleanValues(): void
    {
        $config = PaymentMethodConfig::factory()->create();

        $setupFields = [
            'account_number' => '123456789',
            'is_verified' => true,
            'commission_rate' => 2.5,
        ];

        $response = $this->actingAs($this->admin)
            ->putJson("/api/admin/payment-method-configs/{$config->id}", [
                'setup_fields' => $setupFields,
            ]);

        $response->assertStatus(200);

        $saved = PaymentMethodConfig::find($config->id);

        // Model's json cast returns an array
        $this->assertSame('123456789', $saved->setup_fields['account_number']);
        $this->assertTrue($saved->setup_fields['is_verified']);
        $this->assertSame(2.5, $saved->setup_fields['commission_rate']);
    }

    /**
     * Test setup_fields can be updated multiple times
     */
    public function testSetupFieldsCanBeUpdatedMultipleTimes(): void
    {
        $config = PaymentMethodConfig::factory()->create();

        // First update
        $firstUpdate = [
            'pix_key' => 'initial-key',
            'receiver_name' => 'First Name',
        ];

        $response1 = $this->actingAs($this->admin)
            ->putJson("/api/admin/payment-method-configs/{$config->id}", [
                'setup_fields' => $firstUpdate,
            ]);

        $response1->assertStatus(200);

        $saved1 = PaymentMethodConfig::find($config->id);
        $this->assertSame('initial-key', $saved1->setup_fields['pix_key']);

        // Second update
        $secondUpdate = [
            'pix_key' => 'updated-key',
            'receiver_name' => 'Updated Name',
            'receiver_city_name' => 'Brasília',
        ];

        $response2 = $this->actingAs($this->admin)
            ->putJson("/api/admin/payment-method-configs/{$config->id}", [
                'setup_fields' => $secondUpdate,
            ]);

        $response2->assertStatus(200);

        $saved2 = PaymentMethodConfig::find($config->id);
        $this->assertSame('updated-key', $saved2->setup_fields['pix_key']);
        $this->assertSame('Updated Name', $saved2->setup_fields['receiver_name']);
        $this->assertSame('Brasília', $saved2->setup_fields['receiver_city_name']);
    }

    /**
     * Test large setup_fields JSON is properly stored
     */
    public function testLargeSetupFieldsJsonIsProperlyStored(): void
    {
        $config = PaymentMethodConfig::factory()->create();

        $setupFields = [];

        for ($i = 1; $i <= 100; $i++) {
            $setupFields["field_{$i}"] = "Value for field {$i}";
        }

        $response = $this->actingAs($this->admin)
            ->putJson("/api/admin/payment-method-configs/{$config->id}", [
                'setup_fields' => $setupFields,
            ]);

        $response->assertStatus(200);

        $saved = PaymentMethodConfig::find($config->id);

        // Model's json cast returns an array
        $this->assertCount(100, $saved->setup_fields);
        $this->assertSame('Value for field 50', $saved->setup_fields['field_50']);
        $this->assertSame('Value for field 100', $saved->setup_fields['field_100']);
    }
}
