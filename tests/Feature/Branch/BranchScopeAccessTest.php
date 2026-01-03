<?php

declare(strict_types=1);

namespace Tests\Feature\Branch;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BranchScopeAccessTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branchA;
    protected Branch $branchB;
    protected User $userA;
    protected User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branchA = Branch::create(['name' => 'Branch A', 'code' => 'BA001']);
        $this->branchB = Branch::create(['name' => 'Branch B', 'code' => 'BB001']);

        // Create permissions
        Permission::firstOrCreate(['name' => 'customers.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'customers.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'customers.manage.all', 'guard_name' => 'web']);

        // Create users for each branch
        $this->userA = User::factory()->create([
            'branch_id' => $this->branchA->id,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
        $this->userA->givePermissionTo(['customers.manage', 'customers.view']);
        $this->userA->branches()->attach($this->branchA->id);

        $this->userB = User::factory()->create([
            'branch_id' => $this->branchB->id,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
        $this->userB->givePermissionTo(['customers.manage', 'customers.view']);
        $this->userB->branches()->attach($this->branchB->id);
    }

    public function test_user_can_only_edit_customer_from_own_branch(): void
    {
        // Create customer in branch A
        $customerA = Customer::create([
            'name' => 'Customer A',
            'email' => 'custA@example.com',
            'branch_id' => $this->branchA->id,
        ]);

        // User A should be able to access customer A
        $this->actingAs($this->userA);

        Livewire::test(\App\Livewire\Customers\Form::class, ['customer' => $customerA])
            ->assertStatus(200)
            ->assertSet('editMode', true);
    }

    public function test_user_cannot_edit_customer_from_another_branch(): void
    {
        // Create customer in branch A
        $customerA = Customer::create([
            'name' => 'Customer A',
            'email' => 'custA@example.com',
            'branch_id' => $this->branchA->id,
        ]);

        // User B should NOT be able to access customer from branch A
        $this->actingAs($this->userB);

        // This should abort with 403
        Livewire::test(\App\Livewire\Customers\Form::class, ['customer' => $customerA])
            ->assertForbidden();
    }

    public function test_created_customer_belongs_to_user_branch(): void
    {
        $this->actingAs($this->userA);

        Livewire::test(\App\Livewire\Customers\Form::class)
            ->set('name', 'New Customer')
            ->set('email', 'new@example.com')
            ->call('save');

        $customer = Customer::where('email', 'new@example.com')->first();
        
        $this->assertNotNull($customer);
        $this->assertEquals($this->branchA->id, $customer->branch_id);
    }
}
