<?php

namespace Tests\Feature\Api;

use App\Models\PaymentMethodConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentMethodConfigControllerTest extends TestCase
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
     * Test admin can list payment methods with pagination
     */
    public function test_admin_can_list_payment_methods(): void
    {
        PaymentMethodConfig::factory()->create(['payment_method_key' => 'pix_method_1']);
        PaymentMethodConfig::factory()->create(['payment_method_key' => 'bank_method_1']);
        PaymentMethodConfig::factory()->create(['payment_method_key' => 'card_method_1']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/payment-method-configs');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'links',
            'current_page',
            'per_page',
            'total',
        ]);
        $this->assertCount(3, $response->json('data'));
    }

    /**
     * Test search filters methods by label and payment_method_key
     */
    public function test_search_filters_methods(): void
    {
        PaymentMethodConfig::factory()->create([
            'payment_method_key' => 'pix_offline',
            'label' => 'PIX Offline',
        ]);

        PaymentMethodConfig::factory()->create([
            'payment_method_key' => 'bank_transfer',
            'label' => 'Bank Transfer',
        ]);

        PaymentMethodConfig::factory()->create([
            'payment_method_key' => 'credit_card',
            'label' => 'Credit Card',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/payment-method-configs?search=pix');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('pix_offline', $response->json('data.0.payment_method_key'));
    }

    /**
     * Test filter by active status
     */
    public function test_filter_by_active_status(): void
    {
        PaymentMethodConfig::factory()->active()->create(['payment_method_key' => 'active_method_1']);
        PaymentMethodConfig::factory()->active()->create(['payment_method_key' => 'active_method_2']);
        PaymentMethodConfig::factory()->inactive()->create(['payment_method_key' => 'inactive_method_1']);
        PaymentMethodConfig::factory()->inactive()->create(['payment_method_key' => 'inactive_method_2']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/payment-method-configs?active=true');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));

        foreach ($response->json('data') as $item) {
            $this->assertTrue($item['is_active']);
        }
    }

    /**
     * Test admin can update payment method label and display_order
     */
    public function test_admin_can_update_method(): void
    {
        $paymentMethod = PaymentMethodConfig::factory()->create([
            'label' => 'Original Label',
            'display_order' => 1,
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/admin/payment-method-configs/{$paymentMethod->id}", [
                'label' => 'Updated Label',
                'display_order' => 5,
            ]);

        $response->assertStatus(200);
        $this->assertSame('Updated Label', $response->json('label'));
        $this->assertSame(5, $response->json('display_order'));

        $paymentMethod->refresh();
        $this->assertSame('Updated Label', $paymentMethod->label);
        $this->assertSame(5, $paymentMethod->display_order);
    }

    /**
     * Test toggle activation status
     */
    public function test_toggle_activation_status(): void
    {
        $paymentMethod = PaymentMethodConfig::factory()->active()->create([
            'is_active' => true,
        ]);

        $this->assertTrue($paymentMethod->is_active);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/payment-method-configs/{$paymentMethod->id}/toggle");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'payment_method_config',
        ]);
        $this->assertFalse($response->json('payment_method_config.is_active'));
        $this->assertStringContainsString('deactivated', $response->json('message'));

        $paymentMethod->refresh();
        $this->assertFalse($paymentMethod->is_active);

        $responseToggleBack = $this->actingAs($this->admin)
            ->postJson("/api/admin/payment-method-configs/{$paymentMethod->id}/toggle");

        $responseToggleBack->assertStatus(200);
        $this->assertTrue($responseToggleBack->json('payment_method_config.is_active'));
        $this->assertStringContainsString('activated', $responseToggleBack->json('message'));
    }

    /**
     * Test non-admin cannot access payment method endpoints
     */
    public function test_non_admin_cannot_access(): void
    {
        $response = $this->actingAs($this->viewer)
            ->getJson('/api/admin/payment-method-configs');

        $response->assertStatus(403);
    }

    /**
     * Test unauthenticated user cannot access payment method endpoints
     */
    public function test_unauthenticated_cannot_access(): void
    {
        $response = $this->getJson('/api/admin/payment-method-configs');

        $response->assertStatus(401);
    }

    /**
     * Test pagination works correctly
     */
    public function test_pagination_works_correctly(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            PaymentMethodConfig::factory()->create(['payment_method_key' => "method_$i"]);
        }

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/payment-method-configs?per_page=10');

        $response->assertStatus(200);
        $this->assertCount(10, $response->json('data'));
        $this->assertSame(1, $response->json('current_page'));
        $this->assertSame(10, $response->json('per_page'));
        $this->assertSame(20, $response->json('total'));
    }

    /**
     * Test sorting by display_order and label
     */
    public function test_sorting_by_display_order_and_label(): void
    {
        PaymentMethodConfig::factory()->create([
            'label' => 'Zebra Method',
            'display_order' => 2,
        ]);

        PaymentMethodConfig::factory()->create([
            'label' => 'Apple Method',
            'display_order' => 1,
        ]);

        PaymentMethodConfig::factory()->create([
            'label' => 'Banana Method',
            'display_order' => 1,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/payment-method-configs');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertSame(1, $data[0]['display_order']);
        $this->assertSame(1, $data[1]['display_order']);
        $this->assertSame(2, $data[2]['display_order']);

        $this->assertSame('Apple Method', $data[0]['label']);
        $this->assertSame('Banana Method', $data[1]['label']);
        $this->assertSame('Zebra Method', $data[2]['label']);
    }

    /**
     * Test admin cannot update without required fields
     */
    public function test_admin_cannot_update_without_validation(): void
    {
        $paymentMethod = PaymentMethodConfig::factory()->create();

        $response = $this->actingAs($this->admin)
            ->putJson("/api/admin/payment-method-configs/{$paymentMethod->id}", [
                'label' => '',
                'display_order' => -1,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['label', 'display_order']);
    }

    /**
     * Test toggle requires proper permission
     */
    public function test_toggle_requires_permission(): void
    {
        $paymentMethod = PaymentMethodConfig::factory()->create();

        $this->viewer->removeRole('viewer');

        $togglePermissionRole = Role::firstOrCreate(['name' => 'toggle_user']);
        $togglePermissionRole->syncPermissions([]);
        $this->viewer->assignRole($togglePermissionRole);

        $response = $this->actingAs($this->viewer)
            ->postJson("/api/admin/payment-method-configs/{$paymentMethod->id}/toggle");

        $response->assertStatus(403);
    }

    /**
     * Test search is case insensitive
     */
    public function test_search_is_case_insensitive(): void
    {
        PaymentMethodConfig::factory()->create([
            'payment_method_key' => 'pix_offline',
            'label' => 'PIX Method',
        ]);

        PaymentMethodConfig::factory()->create([
            'payment_method_key' => 'bank_transfer',
            'label' => 'Bank Transfer',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/payment-method-configs?search=PIX');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('pix_offline', $response->json('data.0.payment_method_key'));
    }

    /**
     * Test inactive status filtering
     */
    public function test_filter_by_inactive_status(): void
    {
        PaymentMethodConfig::factory()->active()->create(['payment_method_key' => 'active_test_1']);
        PaymentMethodConfig::factory()->active()->create(['payment_method_key' => 'active_test_2']);
        PaymentMethodConfig::factory()->active()->create(['payment_method_key' => 'active_test_3']);
        PaymentMethodConfig::factory()->inactive()->create(['payment_method_key' => 'inactive_test_1']);
        PaymentMethodConfig::factory()->inactive()->create(['payment_method_key' => 'inactive_test_2']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/payment-method-configs?active=false');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));

        foreach ($response->json('data') as $item) {
            $this->assertFalse($item['is_active']);
        }
    }

    public function test_save_structured_instructions_as_json(): void
    {
        $config = PaymentMethodConfig::factory()->create();

        $instructions = [
            ['key' => 'Chave PIX', 'value' => '123.456.789-10'],
            ['key' => 'Agência', 'value' => '0001'],
            ['key' => 'Conta', 'value' => '123456-7'],
        ];

        $response = $this->actingAs($this->admin)->putJson(
            "/api/admin/payment-method-configs/{$config->id}",
            ['instructions' => $instructions]
        );

        $response->assertStatus(200);
        $this->assertIsString($response->json('instructions'));

        $saved = PaymentMethodConfig::find($config->id);
        $decoded = json_decode($saved->instructions, true);

        $this->assertIsArray($decoded);
        $this->assertCount(3, $decoded);
        $this->assertEquals('Chave PIX', $decoded[0]['key']);
        $this->assertEquals('123.456.789-10', $decoded[0]['value']);
    }

    public function test_retrieve_parsed_instructions(): void
    {
        $instructions = [
            ['key' => 'Titular', 'value' => 'João Silva'],
            ['key' => 'Banco', 'value' => 'Nubank'],
        ];

        $config = PaymentMethodConfig::factory()->create([
            'instructions' => json_encode($instructions),
        ]);

        $response = $this->actingAs($this->admin)->getJson("/api/admin/payment-method-configs/{$config->id}");

        $response->assertStatus(200);
        $this->assertIsString($response->json('instructions'));

        $decoded = json_decode($response->json('instructions'), true);
        $this->assertIsArray($decoded);
        $this->assertEquals('Titular', $decoded[0]['key']);
    }

    public function test_update_instructions_with_empty_pairs(): void
    {
        $config = PaymentMethodConfig::factory()->create([
            'instructions' => json_encode([['key' => 'Old Key', 'value' => 'Old Value']]),
        ]);

        $response = $this->actingAs($this->admin)->putJson(
            "/api/admin/payment-method-configs/{$config->id}",
            ['instructions' => []]
        );

        $response->assertStatus(200);

        $saved = PaymentMethodConfig::find($config->id);
        $this->assertNull($saved->instructions);
    }

    public function test_instructions_with_special_characters(): void
    {
        $config = PaymentMethodConfig::factory()->create();

        $instructions = [
            ['key' => 'Chave PIX (CPF)', 'value' => '123.456.789-10 @ banco'],
            ['key' => 'Descrição', 'value' => 'Dados para transferência (urgente)'],
        ];

        $response = $this->actingAs($this->admin)->putJson(
            "/api/admin/payment-method-configs/{$config->id}",
            ['instructions' => $instructions]
        );

        $response->assertStatus(200);

        $saved = PaymentMethodConfig::find($config->id);
        $decoded = json_decode($saved->instructions, true);

        $this->assertStringContainsString('CPF', $decoded[0]['key']);
        $this->assertStringContainsString('@', $decoded[0]['value']);
        $this->assertStringContainsString('urgente', $decoded[1]['value']);
    }

    public function test_max_instructions_pairs(): void
    {
        $config = PaymentMethodConfig::factory()->create();

        $instructions = array_map(function ($i) {
            return ['key' => "Field {$i}", 'value' => "Value {$i}"];
        }, range(1, 50));

        $response = $this->actingAs($this->admin)->putJson(
            "/api/admin/payment-method-configs/{$config->id}",
            ['instructions' => $instructions]
        );

        $response->assertStatus(200);

        $saved = PaymentMethodConfig::find($config->id);
        $decoded = json_decode($saved->instructions, true);

        $this->assertCount(50, $decoded);
    }
}
