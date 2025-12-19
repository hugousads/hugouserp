<?php

declare(strict_types=1);

namespace Tests\Feature\Purchases;

use App\Livewire\Purchases\Form;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class PurchaseFormValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_is_required_and_validated_before_save(): void
    {
        Gate::define('purchases.manage', fn () => true);

        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $supplier = Supplier::create(['branch_id' => $branch->id, 'name' => 'Vendor']);
        $warehouse = Warehouse::create(['branch_id' => $branch->id, 'name' => 'Main Warehouse']);
        $product = Product::factory()->create(['branch_id' => $branch->id]);

        Livewire::actingAs($user)
            ->test(Form::class)
            ->set('supplier_id', (string) $supplier->id)
            ->set('warehouse_id', '')
            ->set('items', [
                [
                    'product_id' => $product->id,
                    'qty' => 1,
                    'unit_cost' => 10,
                    'discount' => 0,
                    'tax_rate' => 0,
                ],
            ])
            ->call('save')
            ->assertHasErrors(['warehouse_id' => 'required']);

        $this->assertDatabaseMissing('purchases', [
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
        ]);
    }
}
