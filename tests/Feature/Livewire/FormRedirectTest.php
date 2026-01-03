<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FormRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create(['name' => 'Test Branch', 'code' => 'TB001']);

        // Create permissions
        Permission::firstOrCreate(['name' => 'customers.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'customers.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'suppliers.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'suppliers.view', 'guard_name' => 'web']);

        $this->user = User::factory()->create([
            'branch_id' => $this->branch->id,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        $this->user->givePermissionTo(['customers.manage', 'customers.view', 'suppliers.manage', 'suppliers.view']);
    }

    public function test_customer_create_redirects_to_index_with_success_message(): void
    {
        $this->actingAs($this->user);

        // Attach user to branch via relationship
        $this->user->branches()->attach($this->branch->id);

        Livewire::test(\App\Livewire\Customers\Form::class)
            ->set('name', 'Test Customer')
            ->set('email', 'test@example.com')
            ->set('phone', '1234567890')
            ->call('save')
            ->assertRedirect(route('customers.index'))
            ->assertSessionHas('success');
    }

    public function test_customer_update_redirects_to_index_with_success_message(): void
    {
        $this->actingAs($this->user);

        // Attach user to branch via relationship
        $this->user->branches()->attach($this->branch->id);

        $customer = Customer::create([
            'name' => 'Existing Customer',
            'email' => 'existing@example.com',
            'branch_id' => $this->branch->id,
        ]);

        Livewire::test(\App\Livewire\Customers\Form::class, ['customer' => $customer])
            ->set('name', 'Updated Customer')
            ->call('save')
            ->assertRedirect(route('customers.index'))
            ->assertSessionHas('success');
    }

    public function test_supplier_create_redirects_to_index_with_success_message(): void
    {
        $this->actingAs($this->user);

        // Attach user to branch via relationship
        $this->user->branches()->attach($this->branch->id);

        Livewire::test(\App\Livewire\Suppliers\Form::class)
            ->set('name', 'Test Supplier')
            ->set('email', 'supplier@example.com')
            ->call('save')
            ->assertRedirect(route('suppliers.index'))
            ->assertSessionHas('success');
    }
}
