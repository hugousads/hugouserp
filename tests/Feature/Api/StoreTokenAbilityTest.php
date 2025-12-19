<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoreTokenAbilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_write_operations_require_explicit_abilities(): void
    {
        Storage::fake('private');

        $branch = Branch::factory()->create();

        $store = Store::create([
            'name' => 'Webhook Store',
            'type' => Store::TYPE_CUSTOM,
            'url' => 'https://example.test',
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $token = $store->generateApiToken('readonly', ['products.read']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token->token,
        ])->postJson('/api/v1/products', [
            'name' => 'Blocked Product',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('products', 0);
    }
}
