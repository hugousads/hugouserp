<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\BillOfMaterial;
use App\Models\Branch;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BomCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Branch $branch;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create(['name' => 'Test Branch', 'code' => 'TB001']);
        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->product = Product::create([
            'name' => 'Finished Product',
            'code' => 'FIN001',
            'type' => 'stock',
            'default_price' => 1000,
            'branch_id' => $this->branch->id,
        ]);
    }

    protected function createBom(array $overrides = []): BillOfMaterial
    {
        static $counter = 0;
        $counter++;

        return BillOfMaterial::create(array_merge([
            'product_id' => $this->product->id,
            'bom_number' => 'BOM-' . str_pad((string) $counter, 6, '0', STR_PAD_LEFT),
            'name' => 'BOM for Product',
            'quantity' => 1,
            'status' => 'active',
            'branch_id' => $this->branch->id,
        ], $overrides));
    }

    public function test_can_create_bom(): void
    {
        $bom = $this->createBom();

        $this->assertDatabaseHas('bills_of_materials', ['name' => 'BOM for Product']);
    }

    public function test_can_read_bom(): void
    {
        $bom = $this->createBom();

        $found = BillOfMaterial::find($bom->id);
        $this->assertNotNull($found);
    }

    public function test_can_update_bom(): void
    {
        $bom = $this->createBom();

        $bom->update(['status' => 'archived']);
        $this->assertDatabaseHas('bills_of_materials', ['id' => $bom->id, 'status' => 'archived']);
    }

    public function test_can_delete_bom(): void
    {
        $bom = $this->createBom();

        $bom->delete();
        $this->assertSoftDeleted('bills_of_materials', ['id' => $bom->id]);
    }
}
