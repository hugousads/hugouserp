<?php

declare(strict_types=1);

namespace Tests\Feature\Purchases;

use App\Livewire\Purchases\Show;
use App\Models\Branch;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PurchaseAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function makeWarehouse(Branch $branch): Warehouse
    {
        return Warehouse::create([
            'name' => 'Warehouse '.$branch->id,
            'branch_id' => $branch->id,
        ]);
    }

    private function makeSupplier(Branch $branch): Supplier
    {
        return Supplier::create([
            'branch_id' => $branch->id,
            'name' => 'Vendor '.$branch->id,
        ]);
    }

    private function makePurchase(Branch $branch, Warehouse $warehouse, Supplier $supplier): Purchase
    {
        return Purchase::create([
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'status' => 'draft',
        ]);
    }

    public function test_user_cannot_view_purchase_from_another_branch(): void
    {
        Gate::define('purchases.view', fn () => true);

        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branchA->id]);

        $purchase = $this->makePurchase(
            $branchB,
            $this->makeWarehouse($branchB),
            $this->makeSupplier($branchB)
        );

        $this->actingAs($user);
        $this->expectException(HttpException::class);

        Livewire::test(Show::class, ['purchase' => $purchase]);
    }
}
