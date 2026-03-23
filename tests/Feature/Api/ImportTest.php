<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use App\Models\ImportPlan;
use App\Models\ImportPlanRow;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

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

        $this->client = Client::factory()->create();
        $this->wallet = Wallet::factory()->create(['client_id' => $this->client->id]);
    }

    private function seedPermissions(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'import.view',
            'import.view_any',
            'import.create',
            'import.update',
            'import.confirm',
            'import.cancel',
            'import.delete',
            'wallet.view',
            'wallet.view_any',
            'ledger.view',
            'ledger.view_any',
            'ledger.credit',
            'ledger.debit',
            'ledger.adjust',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions($permissions);

        $operator = Role::firstOrCreate(['name' => 'operator']);
        $operator->syncPermissions([
            'import.view',
            'import.view_any',
            'import.create',
            'import.update',
            'import.confirm',
            'import.cancel',
            'wallet.view',
            'wallet.view_any',
            'ledger.view',
            'ledger.view_any',
            'ledger.debit',
        ]);
    }

    public function testUserCanUploadCsvFile(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('test.csv', 100, 'text/csv');

        $response = $this->actingAs($this->admin)->postJson('/api/import-plans', [
            'wallet_id' => $this->wallet->id,
            'file' => $file,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'user_id',
            'wallet_id',
            'status',
            'original_filename',
            'summary',
        ]);

        $this->assertDatabaseHas('import_plans', [
            'user_id' => $this->admin->id,
            'wallet_id' => $this->wallet->id,
            'original_filename' => 'test.csv',
        ]);
    }

    public function testUserCannotUploadWithoutPermission(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('test.csv', 100, 'text/csv');

        $userWithoutPermission = User::factory()->create();

        $response = $this->actingAs($userWithoutPermission)->postJson('/api/import-plans', [
            'wallet_id' => $this->wallet->id,
            'file' => $file,
        ]);

        $response->assertStatus(403);
    }

    public function testImportPlanValidatesRows(): void
    {
        $importPlan = ImportPlan::factory()->create([
            'wallet_id' => $this->wallet->id,
            'user_id' => $this->admin->id,
        ]);

        ImportPlanRow::factory(3)->create([
            'import_plan_id' => $importPlan->id,
            'is_valid' => true,
            'validation_errors' => [],
            'hours' => 2.5,
        ]);

        ImportPlanRow::factory()->create([
            'import_plan_id' => $importPlan->id,
            'is_valid' => false,
            'validation_errors' => ['Hours cannot be zero.'],
            'hours' => 0,
        ]);

        // Recalculate summary
        $this->app->make(\App\Services\ImportService::class)->validateRows($importPlan);

        $response = $this->actingAs($this->admin)->getJson("/api/import-plans/{$importPlan->id}");

        $response->assertStatus(200);

        $data = $response->json();

        $this->assertEquals('pending', $data['status']);
        $this->assertEquals(4, $data['summary']['total_rows']);
        $this->assertEquals(3, $data['summary']['valid_rows']);
        $this->assertEquals(1, $data['summary']['invalid_rows']);
    }

    public function testCanUpdateRowInImportPlan(): void
    {
        $importPlan = ImportPlan::factory()->create([
            'wallet_id' => $this->wallet->id,
            'user_id' => $this->admin->id,
        ]);

        $row = ImportPlanRow::factory()->create([
            'import_plan_id' => $importPlan->id,
            'title' => 'Original Title',
            'hours' => 5,
        ]);

        $response = $this->actingAs($this->admin)->putJson("/api/import-plans/rows/{$row->id}", [
            'title' => 'Updated Title',
            'hours' => 7.5,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('import_plan_rows', [
            'id' => $row->id,
            'title' => 'Updated Title',
            'hours' => 7.5,
        ]);
    }

    public function testCanAddRowToImportPlan(): void
    {
        $importPlan = ImportPlan::factory()->create([
            'wallet_id' => $this->wallet->id,
            'user_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->postJson("/api/import-plans/{$importPlan->id}/rows", [
            'reference_date' => now()->format('Y-m-d'),
            'title' => 'New Row',
            'hours' => 2.5,
            'description' => 'Test description',
            'input_type' => 'debit',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'data' => [
                'id',
                'row_number',
                'title',
                'hours',
                'input_type',
            ],
        ]);

        $this->assertDatabaseHas('import_plan_rows', [
            'import_plan_id' => $importPlan->id,
            'title' => 'New Row',
            'hours' => 2.5,
            'input_type' => 'debit',
        ]);
    }

    public function testCanDeleteRowFromImportPlan(): void
    {
        $importPlan = ImportPlan::factory()->create([
            'wallet_id' => $this->wallet->id,
            'user_id' => $this->admin->id,
        ]);

        $row = ImportPlanRow::factory()->create(['import_plan_id' => $importPlan->id]);

        $response = $this->actingAs($this->admin)->deleteJson("/api/import-plans/rows/{$row->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('import_plan_rows', ['id' => $row->id]);
    }

    public function testCanConfirmValidImportPlan(): void
    {
        $importPlan = ImportPlan::factory()
            ->validated()
            ->create([
                'wallet_id' => $this->wallet->id,
                'user_id' => $this->admin->id,
            ]);

        ImportPlanRow::factory(3)->create([
            'import_plan_id' => $importPlan->id,
            'hours' => 2.5,
            'is_valid' => true,
            'validation_errors' => [],
            'input_type' => 'debit',
        ]);

        $response = $this->actingAs($this->admin)->postJson("/api/import-plans/{$importPlan->id}/confirm");

        $response->assertStatus(200);

        $importPlan->refresh();

        $this->assertEquals('confirmed', $importPlan->status);
        $this->assertNotNull($importPlan->confirmed_at);
        $this->assertEquals(3, LedgerEntry::where('wallet_id', $this->wallet->id)->count());
    }

    public function testCannotConfirmImportPlanWithErrors(): void
    {
        $importPlan = ImportPlan::factory()->create([
            'wallet_id' => $this->wallet->id,
            'user_id' => $this->admin->id,
        ]);

        ImportPlanRow::factory(2)->create([
            'import_plan_id' => $importPlan->id,
            'is_valid' => true,
            'validation_errors' => [],
        ]);

        ImportPlanRow::factory()->create([
            'import_plan_id' => $importPlan->id,
            'is_valid' => false,
            'validation_errors' => ['Invalid date.'],
        ]);

        $response = $this->actingAs($this->admin)->postJson("/api/import-plans/{$importPlan->id}/confirm");

        $response->assertStatus(422);

        $importPlan->refresh();

        $this->assertNotEquals('confirmed', $importPlan->status);
    }

    public function testCanCancelImportPlan(): void
    {
        $importPlan = ImportPlan::factory()->create([
            'wallet_id' => $this->wallet->id,
            'user_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->postJson("/api/import-plans/{$importPlan->id}/cancel");

        $response->assertStatus(200);

        $importPlan->refresh();

        $this->assertEquals('cancelled', $importPlan->status);
    }

    public function testCannotCancelConfirmedImportPlan(): void
    {
        $importPlan = ImportPlan::factory()
            ->confirmed()
            ->create([
                'wallet_id' => $this->wallet->id,
                'user_id' => $this->admin->id,
            ]);

        $response = $this->actingAs($this->admin)->postJson("/api/import-plans/{$importPlan->id}/cancel");

        $response->assertStatus(422);
    }

    public function testCalculateHoursFromTimeRange(): void
    {
        $importPlan = ImportPlan::factory()->create([
            'wallet_id' => $this->wallet->id,
            'user_id' => $this->admin->id,
        ]);

        $startTime = now()->setTime(8, 0, 0);
        $endTime = $startTime->copy()->setTime(10, 30, 0);

        $response = $this->actingAs($this->admin)->postJson("/api/import-plans/{$importPlan->id}/rows", [
            'reference_date' => now()->format('Y-m-d'),
            'start_time' => $startTime->format('Y-m-d H:i:s'),
            'end_time' => $endTime->format('Y-m-d H:i:s'),
            'title' => 'Time-based Entry',
            'input_type' => 'debit',
        ]);

        $response->assertStatus(201);

        $row = ImportPlanRow::find($response->json('data.id'));

        $this->assertEquals(2.5, $row->hours);
    }

    public function testImportPlanWithDifferentInputTypes(): void
    {
        $importPlan = ImportPlan::factory()->create([
            'wallet_id' => $this->wallet->id,
            'user_id' => $this->admin->id,
        ]);

        $debitResponse = $this->actingAs($this->admin)->postJson("/api/import-plans/{$importPlan->id}/rows", [
            'reference_date' => now()->format('Y-m-d'),
            'title' => 'Debit Entry',
            'hours' => 2,
            'input_type' => 'debit',
        ]);

        $creditResponse = $this->actingAs($this->admin)->postJson("/api/import-plans/{$importPlan->id}/rows", [
            'reference_date' => now()->format('Y-m-d'),
            'title' => 'Credit Entry',
            'hours' => 5,
            'input_type' => 'credit',
        ]);

        $adjustmentResponse = $this->actingAs($this->admin)->postJson("/api/import-plans/{$importPlan->id}/rows", [
            'reference_date' => now()->format('Y-m-d'),
            'title' => 'Adjustment Entry',
            'hours' => 1.5,
            'input_type' => 'adjustment',
        ]);

        $debitResponse->assertStatus(201);
        $creditResponse->assertStatus(201);
        $adjustmentResponse->assertStatus(201);

        $this->assertDatabaseHas('import_plan_rows', [
            'title' => 'Debit Entry',
            'input_type' => 'debit',
        ]);

        $this->assertDatabaseHas('import_plan_rows', [
            'title' => 'Credit Entry',
            'input_type' => 'credit',
        ]);

        $this->assertDatabaseHas('import_plan_rows', [
            'title' => 'Adjustment Entry',
            'input_type' => 'adjustment',
        ]);
    }

    public function testImportRowWithTags(): void
    {
        $importPlan = ImportPlan::factory()->create([
            'wallet_id' => $this->wallet->id,
            'user_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->postJson("/api/import-plans/{$importPlan->id}/rows", [
            'reference_date' => now()->format('Y-m-d'),
            'title' => 'Tagged Entry',
            'hours' => 3,
            'tags' => ['work', 'urgent', 'client-a'],
        ]);

        $response->assertStatus(201);

        $rowId = $response->json('data.id');
        $row = ImportPlanRow::find($rowId);

        $this->assertNotNull($row->tags);
        $this->assertTrue(is_array($row->tags));
    }

    public function testValidationErrorsAreCapturedForZeroHours(): void
    {
        $importPlan = ImportPlan::factory()->create([
            'wallet_id' => $this->wallet->id,
            'user_id' => $this->admin->id,
        ]);

        // Test that rows created directly in DB with zero hours are marked invalid
        $row = ImportPlanRow::factory()->create([
            'import_plan_id' => $importPlan->id,
            'hours' => 0,
        ]);

        // Manually validate the row using the service
        $this->app->make(\App\Services\ImportService::class)->validateRows($importPlan);

        $row->refresh();

        $this->assertFalse($row->is_valid);
        $this->assertContains('Hours cannot be zero.', $row->validation_errors);
    }

    public function testInputTypeValidation(): void
    {
        $importPlan = ImportPlan::factory()->create([
            'wallet_id' => $this->wallet->id,
            'user_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->postJson("/api/import-plans/{$importPlan->id}/rows", [
            'reference_date' => now()->format('Y-m-d'),
            'title' => 'Test Entry',
            'hours' => 2,
            'input_type' => 'debit',
        ]);

        $response->assertStatus(201);

        $row = ImportPlanRow::find($response->json('data.id'));

        $this->assertEquals('debit', $row->input_type);
        $this->assertTrue($row->is_valid);
    }

    public function testEndTimeBeforeStartTimeValidation(): void
    {
        $importPlan = ImportPlan::factory()->create([
            'wallet_id' => $this->wallet->id,
            'user_id' => $this->admin->id,
        ]);

        // Create row with invalid time range directly in DB
        $startTime = now()->setTime(10, 0, 0);
        $endTime = $startTime->copy()->setTime(8, 0, 0);

        $row = ImportPlanRow::factory()->create([
            'import_plan_id' => $importPlan->id,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'hours' => 0,
        ]);

        // Validate the row
        $this->app->make(\App\Services\ImportService::class)->validateRows($importPlan);

        $row->refresh();

        $this->assertFalse($row->is_valid);
        $this->assertContains('End time must be after start time.', $row->validation_errors);
    }

    public function testImportPlanListingFiltersByStatus(): void
    {
        ImportPlan::factory()
            ->count(2)
            ->validated()
            ->create(['user_id' => $this->admin->id]);

        ImportPlan::factory()
            ->create(['user_id' => $this->admin->id, 'status' => 'pending']);

        $response = $this->actingAs($this->admin)->getJson('/api/import-plans?status=validated');

        $response->assertStatus(200);

        $plans = $response->json('data');

        $this->assertEquals(2, count($plans));
        $this->assertTrue(collect($plans)->every(fn ($plan) => $plan['status'] === 'validated'));
    }

    public function testImportPlanListingFiltersByWallet(): void
    {
        $wallet1 = Wallet::factory()->create();
        $wallet2 = Wallet::factory()->create();

        ImportPlan::factory()
            ->count(3)
            ->create(['user_id' => $this->admin->id, 'wallet_id' => $wallet1->id]);

        ImportPlan::factory()
            ->create(['user_id' => $this->admin->id, 'wallet_id' => $wallet2->id]);

        $response = $this->actingAs($this->admin)->getJson("/api/import-plans?wallet_id={$wallet1->id}");

        $response->assertStatus(200);

        $plans = $response->json('data');

        $this->assertEquals(3, count($plans));
        $this->assertTrue(collect($plans)->every(fn ($plan) => $plan['wallet_id'] === $wallet1->id));
    }

    public function testCanDownloadTemplate(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/import-plans/template/download?format=csv');

        $response->assertStatus(200);
    }
}
