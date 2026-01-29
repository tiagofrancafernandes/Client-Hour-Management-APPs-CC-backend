<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use App\Models\Tag;
use App\Models\Timer;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TimerTest extends TestCase
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
        $this->wallet = Wallet::factory()->create([
            'client_id' => $this->client->id,
        ]);
    }

    private function seedPermissions(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'timer.view',
            'timer.view_any',
            'timer.create',
            'timer.update',
            'timer.confirm',
            'timer.delete',
            'wallet.view',
            'wallet.view_any',
            'ledger.view',
            'ledger.view_any',
            'ledger.debit',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions($permissions);

        $operator = Role::firstOrCreate(['name' => 'operator']);
        $operator->syncPermissions([
            'timer.view',
            'timer.view_any',
            'timer.create',
            'timer.update',
            'timer.confirm',
            'wallet.view',
            'wallet.view_any',
            'ledger.view',
            'ledger.view_any',
            'ledger.debit',
        ]);

        $viewer = Role::firstOrCreate(['name' => 'viewer']);
        $viewer->syncPermissions([
            'timer.view',
            'wallet.view',
            'wallet.view_any',
            'ledger.view',
            'ledger.view_any',
        ]);
    }

    public function testRequiresAuthenticationForTimerEndpoints(): void
    {
        $response = $this->getJson('/api/timers');
        $response->assertUnauthorized();

        $response = $this->getJson('/api/timers/active');
        $response->assertUnauthorized();

        $response = $this->postJson('/api/timers', [
            'wallet_id' => $this->wallet->id,
        ]);
        $response->assertUnauthorized();
    }

    public function testCanStartTimer(): void
    {
        $response = $this->actingAs($this->operator)
            ->postJson('/api/timers', [
                'wallet_id' => $this->wallet->id,
                'title' => 'Development work',
                'description' => 'Working on feature X',
            ]);

        $response->assertCreated();
        $response->assertJsonFragment([
            'status' => 'running',
            'title' => 'Development work',
        ]);

        $this->assertDatabaseHas('timers', [
            'user_id' => $this->operator->id,
            'wallet_id' => $this->wallet->id,
            'status' => 'running',
        ]);

        $this->assertDatabaseHas('timer_cycles', [
            'timer_id' => $response->json('id'),
        ]);
    }

    public function testCannotStartTimerWithoutPermission(): void
    {
        $response = $this->actingAs($this->viewer)
            ->postJson('/api/timers', [
                'wallet_id' => $this->wallet->id,
            ]);

        $response->assertForbidden();
    }

    public function testCannotStartMultipleTimersSimultaneously(): void
    {
        $this->actingAs($this->operator)
            ->postJson('/api/timers', [
                'wallet_id' => $this->wallet->id,
            ]);

        $response = $this->actingAs($this->operator)
            ->postJson('/api/timers', [
                'wallet_id' => $this->wallet->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'User already has an active timer. Please stop or cancel it first.',
        ]);
    }

    public function testCanGetActiveTimer(): void
    {
        $timer = $this->actingAs($this->operator)
            ->postJson('/api/timers', [
                'wallet_id' => $this->wallet->id,
                'title' => 'Active work',
            ])
            ->json();

        $response = $this->actingAs($this->operator)
            ->getJson('/api/timers/active');

        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $timer['id'],
            'status' => 'running',
        ]);
    }

    public function testActiveTimerReturnsNullWhenNoActiveTimer(): void
    {
        $response = $this->actingAs($this->operator)
            ->getJson('/api/timers/active');

        $response->assertOk();

        $data = $response->json();

        $this->assertTrue($data === null || $data === []);
    }

    public function testCanPauseTimer(): void
    {
        $timer = Timer::factory()->create([
            'user_id' => $this->operator->id,
            'wallet_id' => $this->wallet->id,
            'status' => 'running',
        ]);

        $response = $this->actingAs($this->operator)
            ->postJson("/api/timers/{$timer->id}/pause");

        $response->assertOk();
        $response->assertJsonFragment([
            'status' => 'paused',
        ]);

        $this->assertDatabaseHas('timers', [
            'id' => $timer->id,
            'status' => 'paused',
        ]);
    }

    public function testCanResumeTimer(): void
    {
        $timer = Timer::factory()->create([
            'user_id' => $this->operator->id,
            'wallet_id' => $this->wallet->id,
            'status' => 'paused',
        ]);

        $response = $this->actingAs($this->operator)
            ->postJson("/api/timers/{$timer->id}/resume");

        $response->assertOk();
        $response->assertJsonFragment([
            'status' => 'running',
        ]);

        $this->assertDatabaseHas('timers', [
            'id' => $timer->id,
            'status' => 'running',
        ]);
    }

    public function testCanStopTimer(): void
    {
        $timer = Timer::factory()->create([
            'user_id' => $this->operator->id,
            'wallet_id' => $this->wallet->id,
            'status' => 'running',
        ]);

        $response = $this->actingAs($this->operator)
            ->postJson("/api/timers/{$timer->id}/stop");

        $response->assertOk();
        $response->assertJsonFragment([
            'status' => 'stopped',
        ]);

        $this->assertDatabaseHas('timers', [
            'id' => $timer->id,
            'status' => 'stopped',
        ]);
    }

    public function testCanCancelTimer(): void
    {
        $timer = Timer::factory()->create([
            'user_id' => $this->operator->id,
            'wallet_id' => $this->wallet->id,
            'status' => 'running',
        ]);

        $response = $this->actingAs($this->operator)
            ->postJson("/api/timers/{$timer->id}/cancel");

        $response->assertOk();
        $response->assertJsonFragment([
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('timers', [
            'id' => $timer->id,
            'status' => 'cancelled',
        ]);
    }

    public function testCanConfirmTimerAndCreateLedgerEntry(): void
    {
        $timer = Timer::factory()->create([
            'user_id' => $this->operator->id,
            'wallet_id' => $this->wallet->id,
            'status' => 'stopped',
            'title' => 'Development task',
        ]);

        $response = $this->actingAs($this->operator)
            ->postJson("/api/timers/{$timer->id}/confirm");

        $response->assertOk();
        $response->assertJsonFragment([
            'status' => 'confirmed',
        ]);

        $this->assertDatabaseHas('timers', [
            'id' => $timer->id,
            'status' => 'confirmed',
        ]);

        $this->assertDatabaseHas('ledger_entries', [
            'wallet_id' => $this->wallet->id,
            'title' => 'Development task',
        ]);

        $timer->refresh();

        $this->assertNotNull($timer->ledger_entry_id);
        $this->assertNotNull($timer->confirmed_at);
    }

    public function testCannotConfirmTimerWithoutPermission(): void
    {
        $timer = Timer::factory()->create([
            'user_id' => $this->viewer->id,
            'wallet_id' => $this->wallet->id,
            'status' => 'stopped',
        ]);

        $response = $this->actingAs($this->viewer)
            ->postJson("/api/timers/{$timer->id}/confirm");

        $response->assertForbidden();
    }

    public function testCannotConfirmRunningTimer(): void
    {
        $timer = Timer::factory()->create([
            'user_id' => $this->operator->id,
            'wallet_id' => $this->wallet->id,
            'status' => 'running',
        ]);

        $response = $this->actingAs($this->operator)
            ->postJson("/api/timers/{$timer->id}/confirm");

        $response->assertStatus(422);
    }

    public function testCanUpdateStoppedTimer(): void
    {
        $timer = Timer::factory()->create([
            'user_id' => $this->operator->id,
            'wallet_id' => $this->wallet->id,
            'status' => 'stopped',
            'title' => 'Old title',
        ]);

        $response = $this->actingAs($this->operator)
            ->putJson("/api/timers/{$timer->id}", [
                'title' => 'New title',
                'description' => 'New description',
            ]);

        $response->assertOk();
        $response->assertJsonFragment([
            'title' => 'New title',
            'description' => 'New description',
        ]);

        $this->assertDatabaseHas('timers', [
            'id' => $timer->id,
            'title' => 'New title',
        ]);
    }

    public function testCannotUpdateNonStoppedTimer(): void
    {
        $timer = Timer::factory()->create([
            'user_id' => $this->operator->id,
            'wallet_id' => $this->wallet->id,
            'status' => 'running',
        ]);

        $response = $this->actingAs($this->operator)
            ->putJson("/api/timers/{$timer->id}", [
                'title' => 'New title',
            ]);

        $response->assertStatus(422);
    }

    public function testCanAttachTagsToTimer(): void
    {
        $tag1 = Tag::factory()->create(['name' => 'Development']);
        $tag2 = Tag::factory()->create(['name' => 'Bug Fix']);

        $response = $this->actingAs($this->operator)
            ->postJson('/api/timers', [
                'wallet_id' => $this->wallet->id,
                'title' => 'Tagged work',
                'tags' => [$tag1->id, $tag2->id],
            ]);

        $response->assertCreated();

        $timer = Timer::latest()->first();

        $this->assertCount(2, $timer->tags);
        $this->assertTrue($timer->tags->contains($tag1));
        $this->assertTrue($timer->tags->contains($tag2));
    }

    public function testCanDeleteConfirmedTimer(): void
    {
        $timer = Timer::factory()->create([
            'user_id' => $this->admin->id,
            'wallet_id' => $this->wallet->id,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/timers/{$timer->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('timers', ['id' => $timer->id]);
    }

    public function testCannotDeleteNonConfirmedTimer(): void
    {
        $timer = Timer::factory()->create([
            'user_id' => $this->admin->id,
            'wallet_id' => $this->wallet->id,
            'status' => 'running',
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/timers/{$timer->id}");

        $response->assertStatus(422);
    }

    public function testCannotDeleteTimerWithoutPermission(): void
    {
        $timer = Timer::factory()->create([
            'user_id' => $this->operator->id,
            'wallet_id' => $this->wallet->id,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($this->operator)
            ->deleteJson("/api/timers/{$timer->id}");

        $response->assertForbidden();
    }

    public function testUserCanOnlyManageOwnTimers(): void
    {
        $otherUser = User::factory()->create();
        $otherUser->assignRole('operator');

        $timer = Timer::factory()->create([
            'user_id' => $this->operator->id,
            'wallet_id' => $this->wallet->id,
            'status' => 'running',
        ]);

        $response = $this->actingAs($otherUser)
            ->postJson("/api/timers/{$timer->id}/pause");

        $response->assertForbidden();
    }

    public function testCanListUserTimers(): void
    {
        Timer::factory()->create([
            'user_id' => $this->operator->id,
            'wallet_id' => $this->wallet->id,
            'status' => 'confirmed',
        ]);

        Timer::factory()->create([
            'user_id' => $this->operator->id,
            'wallet_id' => $this->wallet->id,
            'status' => 'running',
        ]);

        $otherUser = User::factory()->create();
        $otherUser->assignRole('operator');
        Timer::factory()->create([
            'user_id' => $otherUser->id,
            'wallet_id' => $this->wallet->id,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($this->operator)
            ->getJson('/api/timers');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function testCanFilterTimersByStatus(): void
    {
        Timer::factory()->create([
            'user_id' => $this->operator->id,
            'wallet_id' => $this->wallet->id,
            'status' => 'confirmed',
        ]);

        Timer::factory()->create([
            'user_id' => $this->operator->id,
            'wallet_id' => $this->wallet->id,
            'status' => 'running',
        ]);

        $response = $this->actingAs($this->operator)
            ->getJson('/api/timers?status=confirmed');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.status', 'confirmed');
    }

    public function testTimerCalculatesTotalSeconds(): void
    {
        $timer = Timer::factory()->create([
            'user_id' => $this->operator->id,
            'wallet_id' => $this->wallet->id,
            'status' => 'stopped',
        ]);

        $cycle1 = $timer->cycles()->create([
            'started_at' => now()->subHours(2),
            'ended_at' => now()->subHours(1),
        ]);

        $cycle2 = $timer->cycles()->create([
            'started_at' => now()->subMinutes(30),
            'ended_at' => now(),
        ]);

        $timer->refresh();

        $expectedSeconds = 3600 + 1800;

        $this->assertEquals($expectedSeconds, $timer->total_seconds);
        $this->assertEquals(1.5, $timer->total_hours);
    }
}
