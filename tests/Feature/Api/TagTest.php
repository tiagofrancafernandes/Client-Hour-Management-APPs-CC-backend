<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use App\Models\LedgerEntry;
use App\Models\Tag;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TagTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $viewer;

    private Client $client;

    private Wallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->viewer = User::factory()->create();
        $this->viewer->assignRole('viewer');

        $this->client = Client::factory()->create();
        $this->wallet = Wallet::factory()->create(['client_id' => $this->client->id]);
    }

    private function seedPermissions(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'tag.view',
            'tag.view_any',
            'tag.create',
            'tag.update',
            'tag.delete',
            'ledger.view',
            'ledger.view_any',
            'ledger.credit',
            'ledger.debit',
            'wallet.view',
            'wallet.view_any',
            'report.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions($permissions);

        $viewer = Role::firstOrCreate(['name' => 'viewer']);
        $viewer->syncPermissions(['tag.view', 'tag.view_any', 'ledger.view', 'ledger.view_any', 'wallet.view', 'wallet.view_any', 'report.view']);
    }

    public function testCanListTags(): void
    {
        $tag1 = Tag::factory()->create(['name' => 'Development']);
        $tag2 = Tag::factory()->create(['name' => 'Bug Fix']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/tags');

        $response->assertOk();
        $response->assertJsonCount(2);
        $response->assertJsonFragment(['name' => 'Development']);
        $response->assertJsonFragment(['name' => 'Bug Fix']);
    }

    public function testCanSearchTags(): void
    {
        Tag::factory()->create(['name' => 'Development']);
        Tag::factory()->create(['name' => 'Design']);
        Tag::factory()->create(['name' => 'Bug Fix']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/tags?search=dev');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['name' => 'Development']);
    }

    public function testCanCreateTag(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/tags', [
                'name' => 'Testing',
            ]);

        $response->assertCreated();
        $response->assertJsonFragment(['name' => 'Testing']);

        $this->assertDatabaseHas('tags', ['name' => 'Testing']);
    }

    public function testCannotCreateTagWithoutPermission(): void
    {
        $response = $this->actingAs($this->viewer)
            ->postJson('/api/tags', [
                'name' => 'Testing',
            ]);

        $response->assertForbidden();
    }

    public function testCannotCreateDuplicateTag(): void
    {
        Tag::factory()->create(['name' => 'Development']);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/tags', [
                'name' => 'Development',
            ]);

        $response->assertStatus(422);
    }

    public function testCanUpdateTag(): void
    {
        $tag = Tag::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/tags/{$tag->id}", [
                'name' => 'New Name',
            ]);

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'New Name']);

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'name' => 'New Name',
        ]);
    }

    public function testCannotUpdateTagWithoutPermission(): void
    {
        $tag = Tag::factory()->create(['name' => 'Development']);

        $response = $this->actingAs($this->viewer)
            ->putJson("/api/tags/{$tag->id}", [
                'name' => 'Updated',
            ]);

        $response->assertForbidden();
    }

    public function testCanDeleteTag(): void
    {
        $tag = Tag::factory()->create(['name' => 'To Delete']);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/tags/{$tag->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }

    public function testCannotDeleteTagWithoutPermission(): void
    {
        $tag = Tag::factory()->create(['name' => 'Protected']);

        $response = $this->actingAs($this->viewer)
            ->deleteJson("/api/tags/{$tag->id}");

        $response->assertForbidden();

        $this->assertDatabaseHas('tags', ['id' => $tag->id]);
    }

    public function testCanAttachTagsToLedgerEntry(): void
    {
        $tag1 = Tag::factory()->create(['name' => 'Development']);
        $tag2 = Tag::factory()->create(['name' => 'Bug Fix']);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/ledger-entries', [
                'wallet_id' => $this->wallet->id,
                'type' => 'credit',
                'hours' => 8.0,
                'title' => 'Development work',
                'description' => 'Implemented new feature',
                'reference_date' => now()->format('Y-m-d'),
                'tags' => [$tag1->id, $tag2->id],
            ]);

        $response->assertCreated();
        $response->assertJsonCount(2, 'entry.tags');

        $entry = LedgerEntry::latest()->first();

        $this->assertCount(2, $entry->tags);
        $this->assertTrue($entry->tags->contains($tag1));
        $this->assertTrue($entry->tags->contains($tag2));
    }

    public function testLedgerEntryIncludesTagsInResponse(): void
    {
        $tag = Tag::factory()->create(['name' => 'Development']);

        $entry = LedgerEntry::factory()->create([
            'wallet_id' => $this->wallet->id,
            'hours' => 8.0,
            'title' => 'Work',
        ]);

        $entry->tags()->attach($tag);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/ledger-entries/{$entry->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'tags');
        $response->assertJsonFragment(['name' => 'Development']);
    }

    public function testCanFilterReportsByTags(): void
    {
        $developmentTag = Tag::factory()->create(['name' => 'Development']);
        $bugFixTag = Tag::factory()->create(['name' => 'Bug Fix']);

        $entry1 = LedgerEntry::factory()->create([
            'wallet_id' => $this->wallet->id,
            'hours' => 8.0,
        ]);
        $entry1->tags()->attach($developmentTag);

        $entry2 = LedgerEntry::factory()->create([
            'wallet_id' => $this->wallet->id,
            'hours' => 4.0,
        ]);
        $entry2->tags()->attach($bugFixTag);

        $entry3 = LedgerEntry::factory()->create([
            'wallet_id' => $this->wallet->id,
            'hours' => 2.0,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/reports?tags[]={$developmentTag->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'entries.data');
        $response->assertJsonPath('entries.data.0.id', $entry1->id);
    }

    public function testDeletingTagDoesNotDeleteLedgerEntries(): void
    {
        $tag = Tag::factory()->create(['name' => 'To Delete']);

        $entry = LedgerEntry::factory()->create([
            'wallet_id' => $this->wallet->id,
            'hours' => 8.0,
        ]);

        $entry->tags()->attach($tag);

        $this->actingAs($this->admin)
            ->deleteJson("/api/tags/{$tag->id}");

        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);

        $this->assertDatabaseHas('ledger_entries', ['id' => $entry->id]);

        $entry->refresh();

        $this->assertCount(0, $entry->tags);
    }

    public function testRequiresAuthenticationForTagEndpoints(): void
    {
        $response = $this->getJson('/api/tags');
        $response->assertUnauthorized();

        $response = $this->postJson('/api/tags', ['name' => 'Test']);
        $response->assertUnauthorized();

        $tag = Tag::factory()->create();
        $response = $this->putJson("/api/tags/{$tag->id}", ['name' => 'Updated']);
        $response->assertUnauthorized();

        $response = $this->deleteJson("/api/tags/{$tag->id}");
        $response->assertUnauthorized();
    }
}
