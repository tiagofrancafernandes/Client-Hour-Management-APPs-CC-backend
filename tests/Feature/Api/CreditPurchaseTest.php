<?php

namespace Tests\Feature\Api;

use App\Enums\CreditPurchaseStatus;
use App\Enums\PaymentStatus;
use App\Models\Client;
use App\Models\CreditPurchase;
use App\Models\CreditPurchasePayment;
use App\Models\User;
use App\Models\Wallet;
use App\PaymentMethods\PaymentMethodRegistry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CreditPurchaseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    private Client $client;

    private Wallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->client = Client::factory()->create();

        $this->wallet = Wallet::factory()->create([
            'client_id' => $this->client->id,
            'currency_code' => 'BRL',
            'credit_purchase_allowed' => true,
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->customer = User::factory()->create([
            'customer_id' => $this->client->id,
        ]);
        $this->customer->assignRole('customer');
    }

    private function seedPermissions(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'credit_purchase.view',
            'credit_purchase.create',
            'credit_purchase.approve',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions($permissions);

        $customer = Role::firstOrCreate(['name' => 'customer']);
        $customer->syncPermissions([]);
    }

    // ─────────────────────────────────────────────────────────────
    // PaymentMethodRegistry unit behavior
    // ─────────────────────────────────────────────────────────────

    public function testRegistryContainsBothDefaultMethods(): void
    {
        $keys = PaymentMethodRegistry::keys();

        $this->assertContains('pix_offline', $keys);
        $this->assertContains('bank_transfer', $keys);
    }

    public function testRegistryFindReturnsCorrectInstance(): void
    {
        $method = PaymentMethodRegistry::find('pix_offline');

        $this->assertNotNull($method);
        $this->assertSame('pix_offline', $method->key());
        $this->assertTrue($method->isOffline());
    }

    public function testRegistryFindReturnsNullForUnknownKey(): void
    {
        $result = PaymentMethodRegistry::find('nonexistent');

        $this->assertNull($result);
    }

    public function testRegistryActiveReturnsOnlyActiveMethods(): void
    {
        $active = PaymentMethodRegistry::active();

        foreach ($active as $method) {
            $this->assertTrue($method->isActive());
        }
    }

    public function testPixOfflineExpiresIn48Hours(): void
    {
        $method = PaymentMethodRegistry::fromKey('pix_offline');
        $expiresAt = $method->expiresAt();

        $this->assertNotNull($expiresAt);
        $this->assertTrue($expiresAt->isAfter(Carbon::now()->addHours(47)));
        $this->assertTrue($expiresAt->isBefore(Carbon::now()->addHours(49)));
    }

    public function testBankTransferExpiresIn7Days(): void
    {
        $method = PaymentMethodRegistry::fromKey('bank_transfer');
        $expiresAt = $method->expiresAt();

        $this->assertNotNull($expiresAt);
        $this->assertTrue($expiresAt->isAfter(Carbon::now()->addDays(6)));
        $this->assertTrue($expiresAt->isBefore(Carbon::now()->addDays(8)));
    }

    public function testRegistryToArrayReturnsAllMethodsAsArrays(): void
    {
        $data = PaymentMethodRegistry::toArray();

        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        foreach ($data as $item) {
            $this->assertArrayHasKey('key', $item);
            $this->assertArrayHasKey('label', $item);
            $this->assertArrayHasKey('is_active', $item);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Credit Purchase creation
    // ─────────────────────────────────────────────────────────────

    public function testAdminCanCreateCreditPurchase(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/credit-purchases', [
                'wallet_id' => $this->wallet->id,
                'total_hours' => 10,
                'total_price' => 500.00,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.wallet_id', $this->wallet->id);
        $response->assertJsonPath('data.total_hours', '10.00');

        $this->assertDatabaseHas('credit_purchases', [
            'wallet_id' => $this->wallet->id,
            'status' => CreditPurchaseStatus::PENDING->value,
        ]);
    }

    public function testCustomerCanCreateCreditPurchaseForOwnWallet(): void
    {
        $response = $this->actingAs($this->customer)
            ->postJson('/api/credit-purchases', [
                'wallet_id' => $this->wallet->id,
                'total_hours' => 5,
                'total_price' => 250.00,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.customer_id', $this->customer->id);
    }

    public function testCustomerCannotCreateCreditPurchaseForAnotherClientWallet(): void
    {
        $otherClient = Client::factory()->create();

        $otherWallet = Wallet::factory()->create([
            'client_id' => $otherClient->id,
            'credit_purchase_allowed' => true,
        ]);

        $response = $this->actingAs($this->customer)
            ->postJson('/api/credit-purchases', [
                'wallet_id' => $otherWallet->id,
                'total_hours' => 5,
                'total_price' => 250.00,
            ]);

        $response->assertStatus(403);
    }

    public function testCannotCreateCreditPurchaseForWalletWithPurchaseDisabled(): void
    {
        $disabledWallet = Wallet::factory()->create([
            'client_id' => $this->client->id,
            'credit_purchase_allowed' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/credit-purchases', [
                'wallet_id' => $disabledWallet->id,
                'total_hours' => 5,
                'total_price' => 250.00,
            ]);

        $response->assertStatus(422);
    }

    // ─────────────────────────────────────────────────────────────
    // Payment creation
    // ─────────────────────────────────────────────────────────────

    public function testCanCreatePaymentWithoutMethod(): void
    {
        $purchase = CreditPurchase::factory()->create([
            'wallet_id' => $this->wallet->id,
            'customer_id' => $this->customer->id,
            'status' => CreditPurchaseStatus::PENDING,
        ]);

        $response = $this->actingAs($this->customer)
            ->postJson("/api/credit-purchases/{$purchase->id}/payments", [
                'payment_method' => null,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.payment_status', PaymentStatus::PENDING->value);
        $response->assertJsonPath('data.payment_method', null);
        $response->assertJsonPath('data.expires_at', null);
    }

    public function testCanCreatePaymentWithPixOfflineMethod(): void
    {
        $purchase = CreditPurchase::factory()->create([
            'wallet_id' => $this->wallet->id,
            'customer_id' => $this->customer->id,
            'status' => CreditPurchaseStatus::PENDING,
        ]);

        $response = $this->actingAs($this->customer)
            ->postJson("/api/credit-purchases/{$purchase->id}/payments", [
                'payment_method' => 'pix_offline',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.payment_method.key', 'pix_offline');

        $this->assertNotNull($response->json('data.expires_at'));
    }

    public function testCanCreatePaymentWithBankTransferMethod(): void
    {
        $purchase = CreditPurchase::factory()->create([
            'wallet_id' => $this->wallet->id,
            'customer_id' => $this->customer->id,
            'status' => CreditPurchaseStatus::PENDING,
        ]);

        $response = $this->actingAs($this->customer)
            ->postJson("/api/credit-purchases/{$purchase->id}/payments", [
                'payment_method' => 'bank_transfer',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.payment_method.key', 'bank_transfer');

        $this->assertNotNull($response->json('data.expires_at'));
    }

    public function testCannotCreateDuplicatePaymentForSamePurchase(): void
    {
        $purchase = CreditPurchase::factory()->create([
            'wallet_id' => $this->wallet->id,
            'customer_id' => $this->customer->id,
            'status' => CreditPurchaseStatus::PENDING,
        ]);

        CreditPurchasePayment::factory()->create([
            'credit_purchase_id' => $purchase->id,
            'payment_status' => PaymentStatus::PENDING,
        ]);

        $response = $this->actingAs($this->customer)
            ->postJson("/api/credit-purchases/{$purchase->id}/payments", []);

        $response->assertStatus(422);
    }

    public function testCustomerCannotCreatePaymentForAnotherCustomerPurchase(): void
    {
        $otherCustomer = User::factory()->create();
        $otherCustomer->assignRole('customer');

        $purchase = CreditPurchase::factory()->create([
            'wallet_id' => $this->wallet->id,
            'customer_id' => $otherCustomer->id,
            'status' => CreditPurchaseStatus::PENDING,
        ]);

        $response = $this->actingAs($this->customer)
            ->postJson("/api/credit-purchases/{$purchase->id}/payments", []);

        $response->assertStatus(403);
    }

    public function testCannotCreatePaymentWithInvalidPaymentMethod(): void
    {
        $purchase = CreditPurchase::factory()->create([
            'wallet_id' => $this->wallet->id,
            'customer_id' => $this->customer->id,
            'status' => CreditPurchaseStatus::PENDING,
        ]);

        $response = $this->actingAs($this->customer)
            ->postJson("/api/credit-purchases/{$purchase->id}/payments", [
                'payment_method' => 'invalid_method',
            ]);

        $response->assertStatus(422);
    }

    // ─────────────────────────────────────────────────────────────
    // Set payment method later
    // ─────────────────────────────────────────────────────────────

    public function testCanSetPaymentMethodLater(): void
    {
        $purchase = CreditPurchase::factory()->create([
            'wallet_id' => $this->wallet->id,
            'customer_id' => $this->customer->id,
            'status' => CreditPurchaseStatus::PENDING,
        ]);

        $payment = CreditPurchasePayment::factory()->create([
            'credit_purchase_id' => $purchase->id,
            'payment_method' => null,
            'payment_status' => PaymentStatus::PENDING,
            'expires_at' => null,
        ]);

        $response = $this->actingAs($this->customer)
            ->putJson("/api/credit-purchases/{$purchase->id}/payments/{$payment->id}/set-method", [
                'payment_method' => 'pix_offline',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.payment_method.key', 'pix_offline');

        $this->assertNotNull($response->json('data.expires_at'));
    }

    public function testSetMethodRequiresPaymentMethodField(): void
    {
        $purchase = CreditPurchase::factory()->create([
            'wallet_id' => $this->wallet->id,
            'customer_id' => $this->customer->id,
            'status' => CreditPurchaseStatus::PENDING,
        ]);

        $payment = CreditPurchasePayment::factory()->create([
            'credit_purchase_id' => $purchase->id,
            'payment_status' => PaymentStatus::PENDING,
        ]);

        $response = $this->actingAs($this->customer)
            ->putJson("/api/credit-purchases/{$purchase->id}/payments/{$payment->id}/set-method", []);

        $response->assertStatus(422);
    }

    public function testCannotSetMethodOnNonPendingPayment(): void
    {
        $purchase = CreditPurchase::factory()->create([
            'wallet_id' => $this->wallet->id,
            'customer_id' => $this->customer->id,
            'status' => CreditPurchaseStatus::APPROVED,
        ]);

        $payment = CreditPurchasePayment::factory()->create([
            'credit_purchase_id' => $purchase->id,
            'payment_method' => 'pix_offline',
            'payment_status' => PaymentStatus::APPROVED,
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/credit-purchases/{$purchase->id}/payments/{$payment->id}/set-method", [
                'payment_method' => 'bank_transfer',
            ]);

        $response->assertStatus(422);
    }

    public function testCannotSetMethodOnExpiredPayment(): void
    {
        $purchase = CreditPurchase::factory()->create([
            'wallet_id' => $this->wallet->id,
            'customer_id' => $this->customer->id,
            'status' => CreditPurchaseStatus::PENDING,
        ]);

        $payment = CreditPurchasePayment::factory()->create([
            'credit_purchase_id' => $purchase->id,
            'payment_status' => PaymentStatus::PENDING,
            'expires_at' => Carbon::now()->subHour(),
        ]);

        $response = $this->actingAs($this->customer)
            ->putJson("/api/credit-purchases/{$purchase->id}/payments/{$payment->id}/set-method", [
                'payment_method' => 'pix_offline',
            ]);

        $response->assertStatus(422);
    }

    public function testCustomerCannotSetMethodForAnotherCustomerPayment(): void
    {
        $otherCustomer = User::factory()->create();
        $otherCustomer->assignRole('customer');

        $purchase = CreditPurchase::factory()->create([
            'wallet_id' => $this->wallet->id,
            'customer_id' => $otherCustomer->id,
            'status' => CreditPurchaseStatus::PENDING,
        ]);

        $payment = CreditPurchasePayment::factory()->create([
            'credit_purchase_id' => $purchase->id,
            'payment_status' => PaymentStatus::PENDING,
        ]);

        $response = $this->actingAs($this->customer)
            ->putJson("/api/credit-purchases/{$purchase->id}/payments/{$payment->id}/set-method", [
                'payment_method' => 'pix_offline',
            ]);

        $response->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────
    // Receipt upload
    // ─────────────────────────────────────────────────────────────

    public function testCanUploadReceiptForPayment(): void
    {
        Storage::fake('local');

        $purchase = CreditPurchase::factory()->create([
            'wallet_id' => $this->wallet->id,
            'customer_id' => $this->customer->id,
            'status' => CreditPurchaseStatus::PENDING,
        ]);

        $payment = CreditPurchasePayment::factory()->create([
            'credit_purchase_id' => $purchase->id,
            'payment_method' => 'pix_offline',
            'payment_status' => PaymentStatus::PENDING,
        ]);

        $file = UploadedFile::fake()->image('receipt.jpg');

        $response = $this->actingAs($this->customer)
            ->postJson("/api/credit-purchases/{$purchase->id}/payments/{$payment->id}/upload-receipt", [
                'receipt' => $file,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Receipt uploaded successfully');

        $this->assertNotNull($response->json('data.pix_receipt_path'));
    }

    public function testReceiptUploadRequiresValidFile(): void
    {
        $purchase = CreditPurchase::factory()->create([
            'wallet_id' => $this->wallet->id,
            'customer_id' => $this->customer->id,
            'status' => CreditPurchaseStatus::PENDING,
        ]);

        $payment = CreditPurchasePayment::factory()->create([
            'credit_purchase_id' => $purchase->id,
            'payment_status' => PaymentStatus::PENDING,
        ]);

        $response = $this->actingAs($this->customer)
            ->postJson("/api/credit-purchases/{$purchase->id}/payments/{$payment->id}/upload-receipt", []);

        $response->assertStatus(422);
    }

    public function testCustomerCannotUploadReceiptForAnotherCustomerPayment(): void
    {
        Storage::fake('local');

        $otherCustomer = User::factory()->create();
        $otherCustomer->assignRole('customer');

        $purchase = CreditPurchase::factory()->create([
            'wallet_id' => $this->wallet->id,
            'customer_id' => $otherCustomer->id,
            'status' => CreditPurchaseStatus::PENDING,
        ]);

        $payment = CreditPurchasePayment::factory()->create([
            'credit_purchase_id' => $purchase->id,
            'payment_status' => PaymentStatus::PENDING,
        ]);

        $file = UploadedFile::fake()->image('receipt.jpg');

        $response = $this->actingAs($this->customer)
            ->postJson("/api/credit-purchases/{$purchase->id}/payments/{$payment->id}/upload-receipt", [
                'receipt' => $file,
            ]);

        $response->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────
    // Payment approval
    // ─────────────────────────────────────────────────────────────

    public function testAdminCanApprovePayment(): void
    {
        $purchase = CreditPurchase::factory()->create([
            'wallet_id' => $this->wallet->id,
            'customer_id' => $this->customer->id,
            'status' => CreditPurchaseStatus::PENDING,
        ]);

        $payment = CreditPurchasePayment::factory()->create([
            'credit_purchase_id' => $purchase->id,
            'payment_method' => 'pix_offline',
            'payment_status' => PaymentStatus::PENDING,
            'expires_at' => Carbon::now()->addHours(24),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/payments/{$payment->id}/approve", [
                'notes' => 'Comprovante verificado',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.payment_status', PaymentStatus::APPROVED->value);

        $this->assertDatabaseHas('credit_purchases', [
            'id' => $purchase->id,
            'status' => CreditPurchaseStatus::APPROVED->value,
        ]);
    }

    public function testCannotApproveAlreadyApprovedPayment(): void
    {
        $purchase = CreditPurchase::factory()->create([
            'wallet_id' => $this->wallet->id,
            'customer_id' => $this->customer->id,
            'status' => CreditPurchaseStatus::APPROVED,
        ]);

        $payment = CreditPurchasePayment::factory()->create([
            'credit_purchase_id' => $purchase->id,
            'payment_status' => PaymentStatus::APPROVED,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/payments/{$payment->id}/approve");

        $response->assertStatus(422);
    }

    public function testCannotApproveExpiredPayment(): void
    {
        $purchase = CreditPurchase::factory()->create([
            'wallet_id' => $this->wallet->id,
            'customer_id' => $this->customer->id,
            'status' => CreditPurchaseStatus::PENDING,
        ]);

        $payment = CreditPurchasePayment::factory()->create([
            'credit_purchase_id' => $purchase->id,
            'payment_status' => PaymentStatus::PENDING,
            'expires_at' => Carbon::now()->subHour(),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/payments/{$payment->id}/approve");

        $response->assertStatus(422);
        $this->assertStringContainsString('expired', strtolower($response->json('message')));
    }

    public function testCustomerCannotApprovePayment(): void
    {
        $purchase = CreditPurchase::factory()->create([
            'wallet_id' => $this->wallet->id,
            'customer_id' => $this->customer->id,
            'status' => CreditPurchaseStatus::PENDING,
        ]);

        $payment = CreditPurchasePayment::factory()->create([
            'credit_purchase_id' => $purchase->id,
            'payment_status' => PaymentStatus::PENDING,
        ]);

        $response = $this->actingAs($this->customer)
            ->postJson("/api/payments/{$payment->id}/approve");

        $response->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────
    // Payment rejection
    // ─────────────────────────────────────────────────────────────

    public function testAdminCanRejectPayment(): void
    {
        $purchase = CreditPurchase::factory()->create([
            'wallet_id' => $this->wallet->id,
            'customer_id' => $this->customer->id,
            'status' => CreditPurchaseStatus::PENDING,
        ]);

        $payment = CreditPurchasePayment::factory()->create([
            'credit_purchase_id' => $purchase->id,
            'payment_status' => PaymentStatus::PENDING,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/payments/{$payment->id}/reject", [
                'notes' => 'Comprovante inválido',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.payment_status', PaymentStatus::REJECTED->value);

        $this->assertDatabaseHas('credit_purchases', [
            'id' => $purchase->id,
            'status' => CreditPurchaseStatus::REJECTED->value,
        ]);
    }

    public function testRejectionRequiresNotes(): void
    {
        $purchase = CreditPurchase::factory()->create([
            'wallet_id' => $this->wallet->id,
            'customer_id' => $this->customer->id,
            'status' => CreditPurchaseStatus::PENDING,
        ]);

        $payment = CreditPurchasePayment::factory()->create([
            'credit_purchase_id' => $purchase->id,
            'payment_status' => PaymentStatus::PENDING,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/payments/{$payment->id}/reject", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['notes']);
    }

    public function testCannotRejectAlreadyRejectedPayment(): void
    {
        $purchase = CreditPurchase::factory()->create([
            'wallet_id' => $this->wallet->id,
            'customer_id' => $this->customer->id,
            'status' => CreditPurchaseStatus::REJECTED,
        ]);

        $payment = CreditPurchasePayment::factory()->create([
            'credit_purchase_id' => $purchase->id,
            'payment_status' => PaymentStatus::REJECTED,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/payments/{$payment->id}/reject", [
                'notes' => 'Tentativa de rejeitar novamente',
            ]);

        $response->assertStatus(422);
    }

    // ─────────────────────────────────────────────────────────────
    // Pending payments list
    // ─────────────────────────────────────────────────────────────

    public function testAdminCanListPendingPayments(): void
    {
        $purchase = CreditPurchase::factory()->create([
            'wallet_id' => $this->wallet->id,
            'customer_id' => $this->customer->id,
            'status' => CreditPurchaseStatus::PENDING,
        ]);

        CreditPurchasePayment::factory()->create([
            'credit_purchase_id' => $purchase->id,
            'payment_status' => PaymentStatus::PENDING,
            'expires_at' => Carbon::now()->addHours(24),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/payments/pending');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'current_page',
            'total',
        ]);
    }

    public function testPendingListExcludesExpiredPayments(): void
    {
        $purchase = CreditPurchase::factory()->create([
            'wallet_id' => $this->wallet->id,
            'customer_id' => $this->customer->id,
            'status' => CreditPurchaseStatus::PENDING,
        ]);

        $expiredPayment = CreditPurchasePayment::factory()->create([
            'credit_purchase_id' => $purchase->id,
            'payment_status' => PaymentStatus::PENDING,
            'expires_at' => Carbon::now()->subHour(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/payments/pending');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertNotContains($expiredPayment->id, $ids);
    }

    public function testPendingListIncludesNonExpiredPayments(): void
    {
        $purchase = CreditPurchase::factory()->create([
            'wallet_id' => $this->wallet->id,
            'customer_id' => $this->customer->id,
            'status' => CreditPurchaseStatus::PENDING,
        ]);

        $validPayment = CreditPurchasePayment::factory()->create([
            'credit_purchase_id' => $purchase->id,
            'payment_status' => PaymentStatus::PENDING,
            'expires_at' => Carbon::now()->addHours(24),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/payments/pending');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($validPayment->id, $ids);
    }

    public function testPendingListIncludesPaymentsWithNoExpiry(): void
    {
        $purchase = CreditPurchase::factory()->create([
            'wallet_id' => $this->wallet->id,
            'customer_id' => $this->customer->id,
            'status' => CreditPurchaseStatus::PENDING,
        ]);

        $noExpiryPayment = CreditPurchasePayment::factory()->create([
            'credit_purchase_id' => $purchase->id,
            'payment_method' => null,
            'payment_status' => PaymentStatus::PENDING,
            'expires_at' => null,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/payments/pending');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($noExpiryPayment->id, $ids);
    }

    public function testCustomerCannotListPendingPayments(): void
    {
        $response = $this->actingAs($this->customer)
            ->getJson('/api/payments/pending');

        $response->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────
    // Credit Purchase listing and retrieval
    // ─────────────────────────────────────────────────────────────

    public function testAdminCanListAllCreditPurchases(): void
    {
        CreditPurchase::factory()->count(3)->create([
            'wallet_id' => $this->wallet->id,
            'customer_id' => $this->customer->id,
            'status' => CreditPurchaseStatus::PENDING,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/credit-purchases');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'total', 'current_page']);
    }

    public function testCustomerSeesOnlyOwnCreditPurchases(): void
    {
        $otherCustomer = User::factory()->create();
        $otherCustomer->assignRole('customer');

        CreditPurchase::factory()->create([
            'wallet_id' => $this->wallet->id,
            'customer_id' => $this->customer->id,
            'status' => CreditPurchaseStatus::PENDING,
        ]);

        CreditPurchase::factory()->create([
            'wallet_id' => $this->wallet->id,
            'customer_id' => $otherCustomer->id,
            'status' => CreditPurchaseStatus::PENDING,
        ]);

        $response = $this->actingAs($this->customer)
            ->getJson('/api/credit-purchases');

        $response->assertStatus(200);

        $customerIds = collect($response->json('data'))->pluck('customer_id')->unique()->all();

        $this->assertCount(1, $customerIds);
        $this->assertEquals($this->customer->id, $customerIds[0]);
    }

    public function testCustomerCanShowOwnCreditPurchase(): void
    {
        $purchase = CreditPurchase::factory()->create([
            'wallet_id' => $this->wallet->id,
            'customer_id' => $this->customer->id,
            'status' => CreditPurchaseStatus::PENDING,
        ]);

        $response = $this->actingAs($this->customer)
            ->getJson("/api/credit-purchases/{$purchase->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('id', $purchase->id);
    }

    public function testCustomerCannotShowAnotherCustomerCreditPurchase(): void
    {
        $otherCustomer = User::factory()->create();
        $otherCustomer->assignRole('customer');

        $purchase = CreditPurchase::factory()->create([
            'wallet_id' => $this->wallet->id,
            'customer_id' => $otherCustomer->id,
            'status' => CreditPurchaseStatus::PENDING,
        ]);

        $response = $this->actingAs($this->customer)
            ->getJson("/api/credit-purchases/{$purchase->id}");

        $response->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────
    // CreditPurchasePayment model behavior
    // ─────────────────────────────────────────────────────────────

    public function testPaymentIsExpiredWhenExpiresAtIsInThePast(): void
    {
        $payment = new CreditPurchasePayment([
            'expires_at' => Carbon::now()->subMinute(),
        ]);

        $this->assertTrue($payment->isExpired());
    }

    public function testPaymentIsNotExpiredWhenExpiresAtIsInTheFuture(): void
    {
        $payment = new CreditPurchasePayment([
            'expires_at' => Carbon::now()->addHour(),
        ]);

        $this->assertFalse($payment->isExpired());
    }

    public function testPaymentIsNotExpiredWhenExpiresAtIsNull(): void
    {
        $payment = new CreditPurchasePayment([
            'expires_at' => null,
        ]);

        $this->assertFalse($payment->isExpired());
    }
}
