<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\AuthenticateStoreToken;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductsStoreGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasColumn('store_tokens', 'deleted_at')) {
            Schema::table('store_tokens', function (Blueprint $table): void {
                $table->softDeletes();
            });
        }
    }

    public function test_store_endpoint_requires_store_context(): void
    {
        $this->withoutMiddleware(AuthenticateStoreToken::class);

        $response = $this->postJson('/api/v1/products', [
            'name' => 'Test Product',
            'sku' => 'SKU-CTX-001',
            'price' => 10,
            'quantity' => 1,
        ]);

        $response->assertStatus(401);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_store_uses_token_branch(): void
    {
        $branch = Branch::factory()->create();
        $store = Store::create([
            'name' => 'Main Store',
            'type' => Store::TYPE_CUSTOM,
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $token = StoreToken::create([
            'store_id' => $store->id,
            'name' => 'Writer',
            'token' => 'tok-'.$store->id,
            'abilities' => ['products.write'],
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token->token)
            ->postJson('/api/v1/products', [
                'name' => 'Scoped Product',
                'sku' => 'SKU-CTX-002',
                'price' => 25,
                'quantity' => 2,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('products', [
            'sku' => 'SKU-CTX-002',
            'branch_id' => $branch->id,
        ]);
    }

    public function test_update_rejects_product_from_other_branch(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        $store = Store::create([
            'name' => 'Branch A Store',
            'type' => Store::TYPE_CUSTOM,
            'branch_id' => $branchA->id,
            'is_active' => true,
        ]);

        $token = StoreToken::create([
            'store_id' => $store->id,
            'name' => 'Writer',
            'token' => 'tok-'.$store->id.'-b',
            'abilities' => ['products.write'],
        ]);

        $product = Product::forceCreate([
            'name' => 'Other Branch Product',
            'sku' => 'SKU-OTHER-1',
            'default_price' => 15,
            'branch_id' => $branchB->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token->token)
            ->putJson('/api/v1/products/'.$product->id, [
                'name' => 'Updated',
            ]);

        $response->assertStatus(404);
        $this->assertEquals('Other Branch Product', $product->fresh()->name);
    }

    public function test_store_rejects_warehouse_from_other_branch(): void
    {
        $this->withExceptionHandling();

        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        $store = Store::create([
            'name' => 'Branch A Store',
            'type' => Store::TYPE_CUSTOM,
            'branch_id' => $branchA->id,
            'is_active' => true,
        ]);

        $token = StoreToken::create([
            'store_id' => $store->id,
            'name' => 'Writer',
            'token' => 'tok-'.$store->id.'-c',
            'abilities' => ['products.write'],
        ]);

        $foreignWarehouse = \App\Models\Warehouse::create([
            'name' => 'Branch B Warehouse',
            'branch_id' => $branchB->id,
            'status' => 'active',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token->token)
            ->postJson('/api/v1/products', [
                'name' => 'Invalid Warehouse Product',
                'sku' => 'SKU-WH-001',
                'price' => 10,
                'quantity' => 1,
                'warehouse_id' => $foreignWarehouse->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['warehouse_id']);
        $this->assertDatabaseCount('products', 0);
    }
}
