<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for scope methods across models
 * Tests ensure scope methods generate correct SQL and return expected results
 */
class ScopeMethodsTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test branch for branch-aware models
        $this->branch = Branch::factory()->create();
    }

    /** @test */
    public function account_scope_active_filters_active_accounts(): void
    {
        // Arrange: Create active and inactive accounts
        Account::factory()->create([
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        Account::factory()->create([
            'branch_id' => $this->branch->id,
            'is_active' => false,
        ]);

        // Act: Query active accounts
        $activeAccounts = Account::active()->get();

        // Assert: Only active account returned
        $this->assertCount(1, $activeAccounts);
        $this->assertTrue($activeAccounts->first()->is_active);
    }

    /** @test */
    public function account_scope_type_filters_by_account_type(): void
    {
        // Arrange: Create accounts of different types
        Account::factory()->create([
            'branch_id' => $this->branch->id,
            'type' => 'asset',
        ]);
        Account::factory()->create([
            'branch_id' => $this->branch->id,
            'type' => 'liability',
        ]);

        // Act: Query asset accounts
        $assetAccounts = Account::type('asset')->get();

        // Assert: Only asset accounts returned
        $this->assertCount(1, $assetAccounts);
        $this->assertEquals('asset', $assetAccounts->first()->type);
    }

    /** @test */
    public function account_scope_category_filters_by_account_category(): void
    {
        // Arrange: Create accounts with different categories
        Account::factory()->create([
            'branch_id' => $this->branch->id,
            'account_category' => 'current_assets',
        ]);
        Account::factory()->create([
            'branch_id' => $this->branch->id,
            'account_category' => 'fixed_assets',
        ]);

        // Act: Query current asset accounts
        $currentAssets = Account::category('current_assets')->get();

        // Assert: Only current asset accounts returned
        $this->assertCount(1, $currentAssets);
        $this->assertEquals('current_assets', $currentAssets->first()->account_category);
    }

    /** @test */
    public function product_scope_low_stock_filters_products_below_threshold(): void
    {
        // Arrange: Create products with different stock levels
        Product::factory()->create([
            'branch_id' => $this->branch->id,
            'stock_quantity' => 5,
            'stock_alert_threshold' => 10,
        ]);
        Product::factory()->create([
            'branch_id' => $this->branch->id,
            'stock_quantity' => 20,
            'stock_alert_threshold' => 10,
        ]);

        // Act: Query low stock products
        $lowStockProducts = Product::lowStock()->get();

        // Assert: Only product below threshold returned
        $this->assertCount(1, $lowStockProducts);
        $this->assertLessThanOrEqual(
            $lowStockProducts->first()->stock_alert_threshold,
            $lowStockProducts->first()->stock_quantity
        );
    }

    /** @test */
    public function product_scope_out_of_stock_filters_zero_quantity_products(): void
    {
        // Arrange: Create in-stock and out-of-stock products
        Product::factory()->create([
            'branch_id' => $this->branch->id,
            'stock_quantity' => 0,
        ]);
        Product::factory()->create([
            'branch_id' => $this->branch->id,
            'stock_quantity' => 10,
        ]);

        // Act: Query out of stock products
        $outOfStockProducts = Product::outOfStock()->get();

        // Assert: Only out of stock product returned
        $this->assertCount(1, $outOfStockProducts);
        $this->assertLessThanOrEqual(0, $outOfStockProducts->first()->stock_quantity);
    }

    /** @test */
    public function product_scope_in_stock_filters_available_products(): void
    {
        // Arrange: Create in-stock and out-of-stock products
        Product::factory()->create([
            'branch_id' => $this->branch->id,
            'stock_quantity' => 10,
        ]);
        Product::factory()->create([
            'branch_id' => $this->branch->id,
            'stock_quantity' => 0,
        ]);

        // Act: Query in stock products
        $inStockProducts = Product::inStock()->get();

        // Assert: Only in stock product returned
        $this->assertCount(1, $inStockProducts);
        $this->assertGreaterThan(0, $inStockProducts->first()->stock_quantity);
    }

    /** @test */
    public function product_scope_expiring_soon_filters_products_within_days(): void
    {
        // Arrange: Create perishable products with different expiry dates
        Product::factory()->create([
            'branch_id' => $this->branch->id,
            'is_perishable' => true,
            'expiry_date' => now()->addDays(5),
        ]);
        Product::factory()->create([
            'branch_id' => $this->branch->id,
            'is_perishable' => true,
            'expiry_date' => now()->addDays(40),
        ]);
        Product::factory()->create([
            'branch_id' => $this->branch->id,
            'is_perishable' => false,
            'expiry_date' => null,
        ]);

        // Act: Query products expiring in 30 days
        $expiringSoonProducts = Product::expiringSoon(30)->get();

        // Assert: Only product expiring within 30 days returned
        $this->assertCount(1, $expiringSoonProducts);
        $this->assertTrue($expiringSoonProducts->first()->is_perishable);
    }

    /** @test */
    public function scope_methods_can_be_chained(): void
    {
        // Arrange: Create multiple products
        Product::factory()->create([
            'branch_id' => $this->branch->id,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);
        Product::factory()->create([
            'branch_id' => $this->branch->id,
            'stock_quantity' => 0,
            'is_active' => true,
        ]);
        Product::factory()->create([
            'branch_id' => $this->branch->id,
            'stock_quantity' => 10,
            'is_active' => false,
        ]);

        // Act: Chain multiple scopes
        $products = Product::active()->inStock()->get();

        // Assert: Only active and in-stock products returned
        $this->assertCount(1, $products);
        $this->assertTrue($products->first()->is_active);
        $this->assertGreaterThan(0, $products->first()->stock_quantity);
    }

    /** @test */
    public function scope_methods_generate_correct_sql(): void
    {
        // Act: Get SQL query from scope
        $query = Account::type('asset')->toSql();

        // Assert: SQL contains correct WHERE clause
        $this->assertStringContainsString('where `type` = ?', $query);
    }
}
