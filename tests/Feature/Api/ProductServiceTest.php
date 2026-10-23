<?php

namespace Tests\Feature\Api;

use App\Models\ProductService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProductServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $permissions = [
            'product_service.view_any',
            'product_service.view',
            'product_service.create',
            'product_service.update',
            'product_service.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $this->adminUser = User::factory()->create();
        $this->adminUser->givePermissionTo($permissions);

        $this->regularUser = User::factory()->create();
    }

    public function testCanListProductsAndServicesWithPagination(): void
    {
        ProductService::factory()->count(25)->create();

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/products-services?per_page=10&page=1');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'current_page',
                'last_page',
                'per_page',
                'total',
            ]);

        $this->assertCount(10, $response->json('data'));
        $this->assertSame(25, $response->json('total'));
    }

    public function testCanListAllProductsAndServicesWithoutPagination(): void
    {
        ProductService::factory()->count(15)->create();

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/products-services?all=1');

        $response->assertOk()
            ->assertJsonStructure(['data']);

        $this->assertCount(15, $response->json('data'));
    }

    public function testCanFilterAndSearchProductsServices(): void
    {
        ProductService::factory()->create([
            'name' => 'Backend API Development',
            'type' => 'service',
            'is_active' => true,
        ]);

        ProductService::factory()->create([
            'name' => 'Mobile App UI Design',
            'type' => 'service',
            'is_active' => false,
        ]);

        ProductService::factory()->create([
            'name' => 'SSL Security Certificate',
            'type' => 'product',
            'is_active' => true,
        ]);

        $searchResponse = $this->actingAs($this->adminUser)
            ->getJson('/api/products-services?search=Backend');
        $searchResponse->assertOk();
        $this->assertCount(1, $searchResponse->json('data'));
        $this->assertSame('Backend API Development', $searchResponse->json('data.0.name'));

        $typeResponse = $this->actingAs($this->adminUser)
            ->getJson('/api/products-services?type=product');
        $typeResponse->assertOk();
        $this->assertCount(1, $typeResponse->json('data'));
        $this->assertSame('SSL Security Certificate', $typeResponse->json('data.0.name'));

        $activeResponse = $this->actingAs($this->adminUser)
            ->getJson('/api/products-services?is_active=0');
        $activeResponse->assertOk();
        $this->assertCount(1, $activeResponse->json('data'));
        $this->assertSame('Mobile App UI Design', $activeResponse->json('data.0.name'));
    }

    public function testCanSortProductsServices(): void
    {
        ProductService::factory()->create([
            'name' => 'Alpha Support',
            'default_price' => 100.00,
        ]);

        ProductService::factory()->create([
            'name' => 'Omega Support',
            'default_price' => 500.00,
        ]);

        $sortDescResponse = $this->actingAs($this->adminUser)
            ->getJson('/api/products-services?sort_by=default_price&sort_direction=desc');

        $sortDescResponse->assertOk();
        $this->assertSame('Omega Support', $sortDescResponse->json('data.0.name'));

        $sortAscResponse = $this->actingAs($this->adminUser)
            ->getJson('/api/products-services?sort_by=name&sort_direction=asc');

        $sortAscResponse->assertOk();
        $this->assertSame('Alpha Support', $sortAscResponse->json('data.0.name'));
    }

    public function testCanCreateProductService(): void
    {
        $payload = [
            'name' => 'Database Optimization',
            'description' => 'PostgreSQL indexing and query tuning',
            'type' => 'service',
            'unit' => 'hour',
            'default_price' => 150.00,
            'default_quantity' => 5.00,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/products-services', $payload);

        $response->assertCreated()
            ->assertJsonFragment([
                'name' => 'Database Optimization',
                'type' => 'service',
            ]);

        $this->assertDatabaseHas('products_services', [
            'name' => 'Database Optimization',
            'unit' => 'hour',
        ]);
    }

    public function testCanUpdateProductService(): void
    {
        $item = ProductService::factory()->create([
            'name' => 'Original Name',
            'default_price' => 80.00,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/products-services/{$item->id}", [
                'name' => 'Updated Name',
                'default_price' => 120.00,
            ]);

        $response->assertOk()
            ->assertJsonFragment([
                'name' => 'Updated Name',
            ]);

        $this->assertDatabaseHas('products_services', [
            'id' => $item->id,
            'name' => 'Updated Name',
            'default_price' => 120.00,
        ]);
    }

    public function testCanDeleteProductService(): void
    {
        $item = ProductService::factory()->create();

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/products-services/{$item->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('products_services', [
            'id' => $item->id,
        ]);
    }

    public function testUnauthorizedUserCannotManageProductsServices(): void
    {
        $response = $this->actingAs($this->regularUser)
            ->getJson('/api/products-services');

        $response->assertForbidden();
    }
}
