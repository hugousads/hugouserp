<?php

declare(strict_types=1);

namespace Tests\Feature\Stores;

use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Test Branch',
            'code' => 'TB001',
        ]);

        $this->user = User::factory()->create([
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_can_create_store(): void
    {
        $this->actingAs($this->user);

        $data = [
            'name' => 'My Shopify Store',
            'type' => Store::TYPE_SHOPIFY,
            'url' => 'https://my-store.myshopify.com',
            'branch_id' => $this->branch->id,
            'is_active' => true,
            'settings' => [
                'sync_products' => true,
                'sync_inventory' => true,
            ],
        ];

        $store = Store::create($data);

        $this->assertDatabaseHas('stores', [
            'name' => 'My Shopify Store',
            'type' => Store::TYPE_SHOPIFY,
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_can_read_store(): void
    {
        $store = Store::create([
            'name' => 'WooCommerce Store',
            'type' => Store::TYPE_WOOCOMMERCE,
            'url' => 'https://woo-store.com',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $found = Store::find($store->id);

        $this->assertNotNull($found);
        $this->assertEquals('WooCommerce Store', $found->name);
    }

    public function test_can_update_store(): void
    {
        $store = Store::create([
            'name' => 'Original Store',
            'type' => Store::TYPE_CUSTOM,
            'url' => 'https://original.com',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $store->update([
            'name' => 'Updated Store',
            'url' => 'https://updated.com',
        ]);

        $this->assertDatabaseHas('stores', [
            'id' => $store->id,
            'name' => 'Updated Store',
            'url' => 'https://updated.com',
        ]);
    }

    public function test_can_delete_store(): void
    {
        $store = Store::create([
            'name' => 'To Delete',
            'type' => Store::TYPE_LARAVEL,
            'url' => 'https://delete.com',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $store->delete();

        $this->assertSoftDeleted('stores', [
            'id' => $store->id,
        ]);
    }

    public function test_store_type_helpers(): void
    {
        $shopify = Store::create([
            'name' => 'Shopify Test',
            'type' => Store::TYPE_SHOPIFY,
            'url' => 'https://test.myshopify.com',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $woo = Store::create([
            'name' => 'WooCommerce Test',
            'type' => Store::TYPE_WOOCOMMERCE,
            'url' => 'https://test.woo.com',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $laravel = Store::create([
            'name' => 'Laravel Test',
            'type' => Store::TYPE_LARAVEL,
            'url' => 'https://test.laravel.com',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->assertTrue($shopify->isShopify());
        $this->assertFalse($shopify->isWooCommerce());

        $this->assertTrue($woo->isWooCommerce());
        $this->assertFalse($woo->isShopify());

        $this->assertTrue($laravel->isLaravel());
        $this->assertFalse($laravel->isShopify());
    }

    public function test_store_has_branch_relationship(): void
    {
        $store = Store::create([
            'name' => 'Store with Branch',
            'type' => Store::TYPE_CUSTOM,
            'url' => 'https://branch.com',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->assertNotNull($store->branch);
        $this->assertEquals($this->branch->id, $store->branch->id);
    }

    public function test_can_generate_api_token(): void
    {
        $store = Store::create([
            'name' => 'Token Store',
            'type' => Store::TYPE_CUSTOM,
            'url' => 'https://token.com',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $token = $store->generateApiToken('Test Token', ['read', 'write']);

        $this->assertNotNull($token);
        $this->assertEquals('Test Token', $token->name);
        $this->assertNotEmpty($token->token);
    }

    public function test_store_settings_are_cast_to_array(): void
    {
        $store = Store::create([
            'name' => 'Settings Store',
            'type' => Store::TYPE_SHOPIFY,
            'url' => 'https://settings.com',
            'branch_id' => $this->branch->id,
            'is_active' => true,
            'settings' => [
                'sync_products' => true,
                'sync_inventory' => false,
                'sync_orders' => true,
            ],
        ]);

        $this->assertIsArray($store->settings);
        $this->assertTrue($store->settings['sync_products']);
        $this->assertFalse($store->settings['sync_inventory']);
    }
}
