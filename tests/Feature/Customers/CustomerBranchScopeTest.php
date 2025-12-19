<?php

declare(strict_types=1);

namespace Tests\Feature\Customers;

use App\Livewire\Customers\Index;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerBranchScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_customers_list_is_scoped_to_user_branch(): void
    {
        $branchA = Branch::factory()->create(['name' => 'Branch A']);
        $branchB = Branch::factory()->create(['name' => 'Branch B']);
        $user = User::factory()->create(['branch_id' => $branchA->id]);

        Customer::create([
            'uuid' => (string) Str::uuid(),
            'code' => 'CUST-A',
            'name' => 'Alice',
            'branch_id' => $branchA->id,
        ]);
        Customer::create([
            'uuid' => (string) Str::uuid(),
            'code' => 'CUST-B',
            'name' => 'Bob',
            'branch_id' => $branchB->id,
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->assertViewHas('customers', function ($customers) use ($branchA) {
                return $customers->every(fn ($customer) => (int) $customer->branch_id === $branchA->id);
            });
    }

    public function test_user_cannot_delete_customer_from_another_branch(): void
    {
        Gate::define('customers.manage', fn () => true);

        $branchA = Branch::factory()->create(['name' => 'Branch A']);
        $branchB = Branch::factory()->create(['name' => 'Branch B']);
        $user = User::factory()->create(['branch_id' => $branchA->id]);

        $otherBranchCustomer = Customer::create([
            'uuid' => (string) Str::uuid(),
            'code' => 'CUST-B',
            'name' => 'Bob',
            'branch_id' => $branchB->id,
        ]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('delete', $otherBranchCustomer->id);
    }
}
