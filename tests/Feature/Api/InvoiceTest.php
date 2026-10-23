<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\ProductService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customerUser;

    private Client $clientA;

    private Client $clientB;

    private ProductService $serviceItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->clientA = Client::factory()->create(['name' => 'Acme Corporation']);
        $this->clientB = Client::factory()->create(['name' => 'Globex Industries']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->customerUser = User::factory()->create([
            'customer_id' => $this->clientA->id,
        ]);
        $this->customerUser->assignRole('customer');

        $this->serviceItem = ProductService::create([
            'name' => 'Software Development',
            'description' => 'Custom software engineering',
            'type' => 'service',
            'unit' => 'hour',
            'default_price' => 150.00,
            'default_quantity' => 10,
            'is_active' => true,
        ]);
    }

    private function seedPermissions(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'invoice.view',
            'invoice.view_any',
            'invoice.create',
            'invoice.update',
            'invoice.delete',
            'invoice.update_any',
            'invoice.hide',
            'invoice.view_draft',
            'product_service.view_any',
            'product_service.create',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions($permissions);

        $customer = Role::firstOrCreate(['name' => 'customer']);
        $customer->syncPermissions(['invoice.view']);
    }

    public function testCanGetNextInvoiceNumberForClient(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson("/api/invoices/next-number?client_id={$this->clientA->id}");

        $response->assertOk()
            ->assertJson([
                'client_id' => $this->clientA->id,
                'next_client_invoice_number' => 1,
                'code' => "C{$this->clientA->id}-I001",
                'formatted_code' => "#C{$this->clientA->id}-I001",
            ]);
    }

    public function testInvoiceCreationGeneratesStandardCode(): void
    {
        $payload = [
            'client_id' => $this->clientA->id,
            'issue_date' => '2026-10-23',
            'currency' => 'USD',
            'status' => 'draft',
            'items' => [
                [
                    'description' => 'Development Sprint 1',
                    'unit' => 'hour',
                    'quantity' => 20,
                    'unit_price' => 100,
                    'product_service_id' => $this->serviceItem->id,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/invoices', $payload);

        $response->assertCreated();

        $data = $response->json();
        $this->assertEquals(1, $data['client_invoice_number']);
        $this->assertEquals("C{$this->clientA->id}-I001", $data['code']);
        $this->assertEquals("#C{$this->clientA->id}-I001", $data['formatted_code']);
        $this->assertEquals('2000.00', $data['total']);
    }

    public function testCanCreateInvoiceWithCustomClientInvoiceNumber(): void
    {
        $payload = [
            'client_id' => $this->clientA->id,
            'client_invoice_number' => 5,
            'issue_date' => '2026-10-23',
            'currency' => 'USD',
            'status' => 'sent',
            'items' => [
                [
                    'description' => 'Custom Consulting',
                    'unit' => 'service',
                    'quantity' => 1,
                    'unit_price' => 500,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/invoices', $payload);

        $response->assertCreated();

        $data = $response->json();
        $this->assertEquals(5, $data['client_invoice_number']);
        $this->assertEquals("C{$this->clientA->id}-I005", $data['code']);
        $this->assertEquals("#C{$this->clientA->id}-I005", $data['formatted_code']);
    }

    public function testCannotCreateDuplicateClientInvoiceNumberForSameClient(): void
    {
        Invoice::create([
            'client_id' => $this->clientA->id,
            'client_invoice_number' => 7,
            'issue_date' => '2026-10-20',
            'currency' => 'USD',
            'status' => 'sent',
        ]);

        $payload = [
            'client_id' => $this->clientA->id,
            'client_invoice_number' => 7,
            'issue_date' => '2026-10-23',
            'currency' => 'USD',
            'items' => [
                [
                    'description' => 'Test Item',
                    'quantity' => 1,
                    'unit_price' => 100,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/invoices', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['client_invoice_number']);
    }

    public function testCanFilterInvoicesBySearchCodeOrClient(): void
    {
        $invoice1 = Invoice::create([
            'client_id' => $this->clientA->id,
            'client_invoice_number' => 12,
            'issue_date' => '2026-10-01',
            'currency' => 'USD',
            'status' => 'sent',
            'notes' => 'Important project note',
        ]);

        $invoice2 = Invoice::create([
            'client_id' => $this->clientB->id,
            'client_invoice_number' => 1,
            'issue_date' => '2026-10-05',
            'currency' => 'USD',
            'status' => 'paid',
        ]);

        // Search by formatted code C{id}-I012
        $searchCode = "C{$this->clientA->id}-I012";
        $response = $this->actingAs($this->admin)->getJson("/api/invoices?search={$searchCode}");
        $response->assertOk();
        $items = $response->json('data');
        $this->assertCount(1, $items);
        $this->assertEquals($invoice1->id, $items[0]['id']);

        // Search by client name
        $responseClient = $this->actingAs($this->admin)->getJson('/api/invoices?search=Globex');
        $responseClient->assertOk();
        $clientItems = $responseClient->json('data');
        $this->assertCount(1, $clientItems);
        $this->assertEquals($invoice2->id, $clientItems[0]['id']);
    }

    public function testCanSortInvoices(): void
    {
        Invoice::create([
            'client_id' => $this->clientA->id,
            'issue_date' => '2026-10-01',
            'total' => 100,
            'currency' => 'USD',
            'status' => 'sent',
        ]);

        Invoice::create([
            'client_id' => $this->clientA->id,
            'issue_date' => '2026-10-15',
            'total' => 500,
            'currency' => 'USD',
            'status' => 'sent',
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/invoices?sort_by=total&sort_direction=desc');
        $response->assertOk();
        $items = $response->json('data');
        $this->assertEquals('500.00', $items[0]['total']);
        $this->assertEquals('100.00', $items[1]['total']);
    }

    public function testMarkdownDownloadIncludesFormattedCode(): void
    {
        $invoice = Invoice::create([
            'client_id' => $this->clientA->id,
            'client_invoice_number' => 5,
            'issue_date' => '2026-10-23',
            'currency' => 'USD',
            'status' => 'sent',
        ]);

        $response = $this->actingAs($this->admin)->getJson("/api/invoices/{$invoice->id}/download-markdown");

        $response->assertOk();
        $markdown = $response->json('markdown');
        $this->assertStringContainsString("# **INVOICE #C{$this->clientA->id}-I005**", $markdown);
        $this->assertStringContainsString("- **Invoice Code:** #C{$this->clientA->id}-I005", $markdown);
    }
}
