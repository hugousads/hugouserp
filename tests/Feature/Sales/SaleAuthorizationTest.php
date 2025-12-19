<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Livewire\Sales\Show;
use App\Models\Branch;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SaleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function makeWarehouse(Branch $branch): Warehouse
    {
        return Warehouse::create([
            'name' => 'Warehouse '.$branch->id,
            'branch_id' => $branch->id,
        ]);
    }

    private function makeSale(Branch $branch, Warehouse $warehouse): Sale
    {
        return Sale::create([
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
        ]);
    }

    public function test_user_cannot_view_sale_from_another_branch(): void
    {
        Gate::define('sales.view', fn () => true);

        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branchA->id]);

        $sale = $this->makeSale($branchB, $this->makeWarehouse($branchB));

        $this->actingAs($user);
        $this->expectException(HttpException::class);

        Livewire::test(Show::class, ['sale' => $sale]);
    }
}
