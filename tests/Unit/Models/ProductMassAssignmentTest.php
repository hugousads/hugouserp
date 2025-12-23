<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductMassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_id_is_fillable(): void
    {
        $product = new Product([
            'name' => 'Test Product',
            'sku' => 'TEST-SKU-001',
            'default_price' => 100,
            'branch_id' => 999,
        ]);

        // branch_id should be assignable during creation to ensure branch scoping is set
        $this->assertSame(999, $product->branch_id);
    }

    public function test_created_by_is_guarded(): void
    {
        $product = new Product([
            'name' => 'Test Product',
            'sku' => 'TEST-SKU-002',
            'default_price' => 100,
            'created_by' => 999,  // This should be ignored
        ]);

        // created_by should NOT be set via mass assignment
        $this->assertNull($product->created_by);
    }

    public function test_updated_by_is_guarded(): void
    {
        $product = new Product([
            'name' => 'Test Product',
            'sku' => 'TEST-SKU-003',
            'default_price' => 100,
            'updated_by' => 999,  // This should be ignored
        ]);

        // updated_by should NOT be set via mass assignment
        $this->assertNull($product->updated_by);
    }

    public function test_uuid_is_guarded(): void
    {
        $product = new Product([
            'name' => 'Test Product',
            'sku' => 'TEST-SKU-004',
            'default_price' => 100,
            'uuid' => 'malicious-uuid',  // This should be ignored
        ]);

        // uuid should NOT be set via mass assignment
        $this->assertNull($product->uuid);
    }

    public function test_code_is_guarded(): void
    {
        $product = new Product([
            'name' => 'Test Product',
            'sku' => 'TEST-SKU-005',
            'default_price' => 100,
            'code' => 'MALICIOUS-CODE',  // This should be ignored
        ]);

        // code should NOT be set via mass assignment
        $this->assertNull($product->code);
    }

    public function test_branch_id_can_be_set_directly(): void
    {
        $product = new Product();
        $product->branch_id = 999;

        $this->assertEquals(999, $product->branch_id);
    }

    public function test_created_by_can_be_set_directly(): void
    {
        $product = new Product();
        $product->created_by = 1;

        $this->assertEquals(1, $product->created_by);
    }

    public function test_fillable_fields_are_allowed(): void
    {
        $product = new Product([
            'name' => 'Test Product',
            'sku' => 'TEST-SKU-FILL',
            'default_price' => 100,
            'status' => 'active',
        ]);

        $this->assertEquals('Test Product', $product->name);
        $this->assertEquals('TEST-SKU-FILL', $product->sku);
        $this->assertEquals(100, $product->default_price);
        $this->assertEquals('active', $product->status);
    }
}
