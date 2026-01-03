<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a branch for testing
        Branch::create(['name' => 'Test Branch', 'code' => 'TB001']);
    }

    protected function createUserWithPermission(string $permission): User
    {
        // Create the permission if it doesn't exist
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);

        // Create user with branch
        $user = User::factory()->create([
            'branch_id' => Branch::first()->id,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        // Give user the permission
        $user->givePermissionTo($permission);

        return $user;
    }

    public function test_first_accessible_route_returns_dashboard_for_user_with_dashboard_permission(): void
    {
        $user = $this->createUserWithPermission('dashboard.view');
        
        $this->actingAs($user);
        
        $route = first_accessible_route_for_user($user);
        
        $this->assertEquals('dashboard', $route);
    }

    public function test_first_accessible_route_returns_pos_for_user_with_only_pos_permission(): void
    {
        // Create user with only POS permission (no dashboard)
        $user = $this->createUserWithPermission('pos.use');
        
        $this->actingAs($user);
        
        $route = first_accessible_route_for_user($user);
        
        $this->assertEquals('pos.terminal', $route);
    }

    public function test_first_accessible_route_returns_inventory_for_user_with_only_inventory_permission(): void
    {
        // Create user with only inventory permission
        $user = $this->createUserWithPermission('inventory.products.view');
        
        $this->actingAs($user);
        
        $route = first_accessible_route_for_user($user);
        
        $this->assertEquals('app.inventory.products.index', $route);
    }

    public function test_first_accessible_route_returns_profile_for_user_with_no_module_permissions(): void
    {
        // Create user with no permissions
        $user = User::factory()->create([
            'branch_id' => Branch::first()->id,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
        
        $this->actingAs($user);
        
        $route = first_accessible_route_for_user($user);
        
        $this->assertEquals('profile.edit', $route);
    }

    public function test_user_with_sales_permission_redirects_to_sales(): void
    {
        // Create user with only sales.view permission
        $user = $this->createUserWithPermission('sales.view');
        
        $this->actingAs($user);
        
        $route = first_accessible_route_for_user($user);
        
        $this->assertEquals('app.sales.index', $route);
    }

    public function test_user_with_customers_permission_redirects_to_customers(): void
    {
        // Create user with only customers.view permission
        $user = $this->createUserWithPermission('customers.view');
        
        $this->actingAs($user);
        
        $route = first_accessible_route_for_user($user);
        
        $this->assertEquals('customers.index', $route);
    }

    public function test_first_accessible_route_returns_login_for_guest(): void
    {
        $route = first_accessible_route_for_user(null);
        
        $this->assertEquals('login', $route);
    }
}
