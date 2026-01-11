<?php

declare(strict_types=1);

namespace Tests\Unit\Models\Scopes;

use App\Models\Branch;
use App\Models\BranchAdmin;
use App\Models\Product;
use App\Models\User;
use App\Services\BranchContextManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test BranchScope to ensure it works correctly without infinite recursion
 */
class BranchScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        BranchContextManager::clearCache();
    }

    protected function tearDown(): void
    {
        BranchContextManager::clearCache();
        
        parent::tearDown();
    }

    public function test_scope_filters_by_user_branch(): void
    {
        $branch1 = Branch::factory()->create(['name' => 'Branch 1']);
        $branch2 = Branch::factory()->create(['name' => 'Branch 2']);

        $user = User::factory()->create(['branch_id' => $branch1->id]);

        // Create products in different branches
        $product1 = Product::factory()->create([
            'branch_id' => $branch1->id,
            'name' => 'Product 1',
        ]);
        
        $product2 = Product::factory()->create([
            'branch_id' => $branch2->id,
            'name' => 'Product 2',
        ]);

        $this->actingAs($user);

        // Should only see products from branch 1
        $products = Product::all();

        $this->assertCount(1, $products);
        $this->assertEquals($product1->id, $products->first()->id);
    }

    public function test_super_admin_sees_all_branches(): void
    {
        $branch1 = Branch::factory()->create();
        $branch2 = Branch::factory()->create();

        $user = User::factory()->create(['branch_id' => $branch1->id]);
        $user->assignRole('Super Admin');

        Product::factory()->create(['branch_id' => $branch1->id]);
        Product::factory()->create(['branch_id' => $branch2->id]);

        $this->actingAs($user);

        $products = Product::all();

        $this->assertCount(2, $products);
    }

    public function test_scope_can_be_disabled(): void
    {
        $branch1 = Branch::factory()->create();
        $branch2 = Branch::factory()->create();

        $user = User::factory()->create(['branch_id' => $branch1->id]);

        Product::factory()->create(['branch_id' => $branch1->id]);
        Product::factory()->create(['branch_id' => $branch2->id]);

        $this->actingAs($user);

        // Without disabling scope - should see 1
        $this->assertCount(1, Product::all());

        // With scope disabled - should see all
        $this->assertCount(2, Product::withoutBranchScope()->get());
    }

    public function test_branch_admin_model_is_excluded_from_scope(): void
    {
        $branch1 = Branch::factory()->create();
        $branch2 = Branch::factory()->create();

        $user = User::factory()->create(['branch_id' => $branch1->id]);

        // Create branch admin records in both branches
        BranchAdmin::create([
            'user_id' => $user->id,
            'branch_id' => $branch1->id,
            'is_active' => true,
        ]);

        BranchAdmin::create([
            'user_id' => $user->id,
            'branch_id' => $branch2->id,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        // BranchAdmin should NOT be filtered by BranchScope
        // User should see all their branch admin records
        $adminRecords = BranchAdmin::where('user_id', $user->id)->get();

        $this->assertCount(2, $adminRecords);
    }

    public function test_user_model_is_excluded_from_scope(): void
    {
        $branch1 = Branch::factory()->create();
        $branch2 = Branch::factory()->create();

        $admin = User::factory()->create(['branch_id' => $branch1->id]);
        $admin->assignRole('Super Admin');

        // Create users in different branches
        User::factory()->create(['branch_id' => $branch1->id]);
        User::factory()->create(['branch_id' => $branch2->id]);

        $this->actingAs($admin);

        // User model should NOT be filtered by BranchScope
        // Should see all users
        $users = User::all();

        $this->assertGreaterThanOrEqual(3, $users->count());
    }

    public function test_no_infinite_recursion_when_checking_branch_admin(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);

        BranchAdmin::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'is_active' => true,
            'can_manage_users' => true,
        ]);

        $this->actingAs($user);

        // This would cause infinite recursion before the fix
        $isBranchAdmin = $user->isBranchAdmin();

        $this->assertTrue($isBranchAdmin);
    }

    public function test_user_with_multiple_branches_sees_all_their_data(): void
    {
        $branch1 = Branch::factory()->create();
        $branch2 = Branch::factory()->create();
        $branch3 = Branch::factory()->create();

        $user = User::factory()->create(['branch_id' => $branch1->id]);
        $user->branches()->attach([$branch2->id, $branch3->id]);

        Product::factory()->create(['branch_id' => $branch1->id]);
        Product::factory()->create(['branch_id' => $branch2->id]);
        Product::factory()->create(['branch_id' => $branch3->id]);
        Product::factory()->create(['branch_id' => Branch::factory()->create()->id]);

        $this->actingAs($user);

        $products = Product::all();

        // Should see products from all 3 branches they have access to
        $this->assertCount(3, $products);
    }
}
