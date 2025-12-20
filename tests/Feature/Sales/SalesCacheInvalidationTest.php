<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SalesCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Branch $branch;
    protected Customer $customer;
    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create(['name' => 'Test Branch', 'code' => 'TB001']);
        $this->warehouse = Warehouse::create([
            'name' => 'Test Warehouse',
            'code' => 'WH001',
            'branch_id' => $this->branch->id,
        ]);
        $this->customer = Customer::create([
            'name' => 'Test Customer',
            'phone' => '1234567890',
            'branch_id' => $this->branch->id,
        ]);
        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
    }

    public function test_cache_is_cleared_when_sale_is_created(): void
    {
        // Pre-populate cache
        $cacheKey = 'sales_stats_' . $this->branch->id;
        Cache::put($cacheKey, ['total_sales' => 0, 'total_revenue' => '0.00'], 300);

        // Verify cache exists
        $this->assertTrue(Cache::has($cacheKey));

        // Create a new sale
        Sale::create([
            'code' => 'SALE-001',
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'status' => 'completed',
            'grand_total' => 100.00,
            'paid_total' => 100.00,
            'due_total' => 0.00,
            'created_by' => $this->user->id,
        ]);

        // Cache should be cleared
        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_cache_is_cleared_when_sale_is_updated(): void
    {
        // Create a sale
        $sale = Sale::create([
            'code' => 'SALE-002',
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'status' => 'pending',
            'grand_total' => 100.00,
            'paid_total' => 0.00,
            'due_total' => 100.00,
            'created_by' => $this->user->id,
        ]);

        // Pre-populate cache
        $cacheKey = 'sales_stats_' . $this->branch->id;
        Cache::put($cacheKey, ['total_sales' => 1, 'total_revenue' => '100.00'], 300);

        // Verify cache exists
        $this->assertTrue(Cache::has($cacheKey));

        // Update the sale
        $sale->update(['status' => 'completed', 'paid_total' => 100.00, 'due_total' => 0.00]);

        // Cache should be cleared
        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_cache_is_cleared_when_sale_is_deleted(): void
    {
        // Create a sale
        $sale = Sale::create([
            'code' => 'SALE-003',
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'status' => 'completed',
            'grand_total' => 100.00,
            'paid_total' => 100.00,
            'due_total' => 0.00,
            'created_by' => $this->user->id,
        ]);

        // Pre-populate cache
        $cacheKey = 'sales_stats_' . $this->branch->id;
        Cache::put($cacheKey, ['total_sales' => 1, 'total_revenue' => '100.00'], 300);

        // Verify cache exists
        $this->assertTrue(Cache::has($cacheKey));

        // Delete the sale
        $sale->delete();

        // Cache should be cleared
        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_only_relevant_branch_cache_is_cleared(): void
    {
        $branch2 = Branch::create(['name' => 'Branch 2', 'code' => 'TB002']);
        $customer2 = Customer::create([
            'name' => 'Test Customer 2',
            'phone' => '0987654321',
            'branch_id' => $branch2->id,
        ]);

        // Pre-populate cache for both branches
        $cacheKey1 = 'sales_stats_' . $this->branch->id;
        $cacheKey2 = 'sales_stats_' . $branch2->id;
        
        Cache::put($cacheKey1, ['total_sales' => 0], 300);
        Cache::put($cacheKey2, ['total_sales' => 0], 300);

        // Verify both caches exist
        $this->assertTrue(Cache::has($cacheKey1));
        $this->assertTrue(Cache::has($cacheKey2));

        // Create a sale in branch 1
        Sale::create([
            'code' => 'SALE-004',
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'status' => 'completed',
            'grand_total' => 100.00,
            'paid_total' => 100.00,
            'due_total' => 0.00,
            'created_by' => $this->user->id,
        ]);

        // Only branch 1 cache should be cleared
        $this->assertFalse(Cache::has($cacheKey1));
        $this->assertTrue(Cache::has($cacheKey2));
    }
}
