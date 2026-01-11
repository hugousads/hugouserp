<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Project;
use App\Models\Scopes\BranchScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HasBranchScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_user_branches_loads_branch_relation_when_missing(): void
    {
        $primaryBranch = Branch::factory()->create();
        $secondaryBranch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $primaryBranch->id]);

        // Attach an additional branch without touching the relation to mimic lazy loading
        $user->branches()->attach($secondaryBranch->id);
        $this->assertFalse($user->relationLoaded('branches'));

        $projectInPrimary = Project::create([
            'branch_id' => $primaryBranch->id,
            'code' => 'PRJ-'.Str::random(5),
            'name' => 'Primary Project',
            'description' => 'Ensures primary branch is included.',
            'status' => 'planning',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addWeek()->format('Y-m-d'),
        ]);

        $projectInSecondary = Project::create([
            'branch_id' => $secondaryBranch->id,
            'code' => 'PRJ-'.Str::random(5),
            'name' => 'Scoped Project',
            'description' => 'Ensures branch scope uses attached branches.',
            'status' => 'planning',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addWeek()->format('Y-m-d'),
        ]);

        $scopedBranchIds = Project::query()
            ->forUserBranches($user)
            ->pluck('branch_id')
            ->all();

        $this->assertContains($projectInSecondary->branch_id, $scopedBranchIds);
        $this->assertContains($primaryBranch->id, $scopedBranchIds);
    }

    public function test_global_branch_scope_filters_by_user_branch(): void
    {
        // Create two branches
        $branchA = Branch::factory()->create(['name' => 'Branch A']);
        $branchB = Branch::factory()->create(['name' => 'Branch B']);

        // Create a regular user in Branch A
        $user = User::factory()->create(['branch_id' => $branchA->id]);

        // Create products in both branches (using withoutGlobalScope to bypass our scope)
        $productA = Product::withoutGlobalScope(BranchScope::class)->create([
            'branch_id' => $branchA->id,
            'name' => 'Product in Branch A',
            'sku' => 'PROD-A-'.Str::random(5),
            'default_price' => 100,
        ]);

        $productB = Product::withoutGlobalScope(BranchScope::class)->create([
            'branch_id' => $branchB->id,
            'name' => 'Product in Branch B',
            'sku' => 'PROD-B-'.Str::random(5),
            'default_price' => 200,
        ]);

        // Act as the user from Branch A
        $this->actingAs($user);

        // Query products - should only see Branch A products due to global scope
        $products = Product::all();

        // Assert user can only see their branch's products
        $this->assertCount(1, $products);
        $this->assertEquals($productA->id, $products->first()->id);
        $this->assertEquals('Product in Branch A', $products->first()->name);
    }

    public function test_super_admin_bypasses_branch_scope(): void
    {
        // Create Super Admin role
        $superAdminRole = Role::findOrCreate('Super Admin', 'web');

        // Create two branches
        $branchA = Branch::factory()->create(['name' => 'Branch A']);
        $branchB = Branch::factory()->create(['name' => 'Branch B']);

        // Create a Super Admin user
        $superAdmin = User::factory()->create(['branch_id' => $branchA->id]);
        $superAdmin->assignRole($superAdminRole);

        // Create products in both branches
        $productA = Product::withoutGlobalScope(BranchScope::class)->create([
            'branch_id' => $branchA->id,
            'name' => 'Product in Branch A',
            'sku' => 'PROD-A-'.Str::random(5),
            'default_price' => 100,
        ]);

        $productB = Product::withoutGlobalScope(BranchScope::class)->create([
            'branch_id' => $branchB->id,
            'name' => 'Product in Branch B',
            'sku' => 'PROD-B-'.Str::random(5),
            'default_price' => 200,
        ]);

        // Act as Super Admin
        $this->actingAs($superAdmin);

        // Query products - Super Admin should see ALL products
        $products = Product::all();

        // Assert Super Admin can see all products from all branches
        $this->assertCount(2, $products);
        $productIds = $products->pluck('id')->toArray();
        $this->assertContains($productA->id, $productIds);
        $this->assertContains($productB->id, $productIds);
    }

    public function test_user_with_multiple_branches_sees_products_from_all_accessible_branches(): void
    {
        // Create three branches
        $branchA = Branch::factory()->create(['name' => 'Branch A']);
        $branchB = Branch::factory()->create(['name' => 'Branch B']);
        $branchC = Branch::factory()->create(['name' => 'Branch C']);

        // Create a user with primary branch A and additional access to branch B
        $user = User::factory()->create(['branch_id' => $branchA->id]);
        $user->branches()->attach($branchB->id);

        // Create products in all branches
        $productA = Product::withoutGlobalScope(BranchScope::class)->create([
            'branch_id' => $branchA->id,
            'name' => 'Product in Branch A',
            'sku' => 'PROD-A-'.Str::random(5),
            'default_price' => 100,
        ]);

        $productB = Product::withoutGlobalScope(BranchScope::class)->create([
            'branch_id' => $branchB->id,
            'name' => 'Product in Branch B',
            'sku' => 'PROD-B-'.Str::random(5),
            'default_price' => 200,
        ]);

        $productC = Product::withoutGlobalScope(BranchScope::class)->create([
            'branch_id' => $branchC->id,
            'name' => 'Product in Branch C',
            'sku' => 'PROD-C-'.Str::random(5),
            'default_price' => 300,
        ]);

        // Act as the user
        $this->actingAs($user);

        // Query products - should see Branch A and B products
        $products = Product::all();

        // Assert user can see products from both accessible branches
        $this->assertCount(2, $products);
        $productIds = $products->pluck('id')->toArray();
        $this->assertContains($productA->id, $productIds);
        $this->assertContains($productB->id, $productIds);
        $this->assertNotContains($productC->id, $productIds);
    }

    public function test_withoutBranchScope_allows_querying_all_branches(): void
    {
        // Create two branches
        $branchA = Branch::factory()->create(['name' => 'Branch A']);
        $branchB = Branch::factory()->create(['name' => 'Branch B']);

        // Create a regular user in Branch A
        $user = User::factory()->create(['branch_id' => $branchA->id]);

        // Create products in both branches
        $productA = Product::withoutGlobalScope(BranchScope::class)->create([
            'branch_id' => $branchA->id,
            'name' => 'Product in Branch A',
            'sku' => 'PROD-A-'.Str::random(5),
            'default_price' => 100,
        ]);

        $productB = Product::withoutGlobalScope(BranchScope::class)->create([
            'branch_id' => $branchB->id,
            'name' => 'Product in Branch B',
            'sku' => 'PROD-B-'.Str::random(5),
            'default_price' => 200,
        ]);

        // Act as the user from Branch A
        $this->actingAs($user);

        // Query products with scope disabled - should see all products
        $products = Product::withoutBranchScope()->get();

        // Assert all products are returned when scope is disabled
        $this->assertCount(2, $products);
        $productIds = $products->pluck('id')->toArray();
        $this->assertContains($productA->id, $productIds);
        $this->assertContains($productB->id, $productIds);
    }
}
