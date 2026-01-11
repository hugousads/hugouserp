<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Branch;
use App\Models\BranchAdmin;
use App\Models\User;
use App\Services\BranchContextManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test BranchContextManager to ensure it prevents infinite recursion
 */
class BranchContextManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear cache before each test
        BranchContextManager::clearCache();
    }

    protected function tearDown(): void
    {
        // Clear cache after each test
        BranchContextManager::clearCache();
        
        parent::tearDown();
    }

    public function test_prevents_infinite_recursion_during_auth(): void
    {
        // Create branch and user
        $branch = Branch::factory()->create(['name' => 'Test Branch']);
        $user = User::factory()->create([
            'branch_id' => $branch->id,
            'email' => 'test@example.com',
        ]);

        // Assign a role using spatie/permission
        $user->assignRole('Branch Manager');

        // Create branch admin record
        BranchAdmin::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'is_active' => true,
            'can_manage_users' => true,
        ]);

        // Authenticate user - this would trigger infinite recursion before the fix
        $this->actingAs($user);

        // Get current user through BranchContextManager
        $currentUser = BranchContextManager::getCurrentUser();

        $this->assertNotNull($currentUser);
        $this->assertEquals($user->id, $currentUser->id);
    }

    public function test_caches_user_within_request(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);

        $this->actingAs($user);

        // First call
        $user1 = BranchContextManager::getCurrentUser();
        
        // Second call should return cached value
        $user2 = BranchContextManager::getCurrentUser();

        $this->assertSame($user1, $user2);
    }

    public function test_returns_null_when_no_authenticated_user(): void
    {
        $user = BranchContextManager::getCurrentUser();

        $this->assertNull($user);
    }

    public function test_gets_accessible_branch_ids_for_regular_user(): void
    {
        $branch1 = Branch::factory()->create(['name' => 'Branch 1']);
        $branch2 = Branch::factory()->create(['name' => 'Branch 2']);
        
        $user = User::factory()->create(['branch_id' => $branch1->id]);
        $user->branches()->attach($branch2->id);

        $this->actingAs($user);

        $branchIds = BranchContextManager::getAccessibleBranchIds();

        $this->assertIsArray($branchIds);
        $this->assertCount(2, $branchIds);
        $this->assertContains($branch1->id, $branchIds);
        $this->assertContains($branch2->id, $branchIds);
    }

    public function test_super_admin_gets_all_branches(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        
        // Assign Super Admin role
        $user->assignRole('Super Admin');

        $this->actingAs($user);

        $branchIds = BranchContextManager::getAccessibleBranchIds();

        // Super Admins return empty array (meaning all branches)
        $this->assertIsArray($branchIds);
    }

    public function test_clears_cache_properly(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);

        $this->actingAs($user);

        // Get user - should be cached
        $user1 = BranchContextManager::getCurrentUser();
        $this->assertNotNull($user1);

        // Clear cache
        BranchContextManager::clearCache();

        // This should trigger a new fetch
        $user2 = BranchContextManager::getCurrentUser();
        $this->assertNotNull($user2);
        $this->assertEquals($user1->id, $user2->id);
    }

    public function test_set_current_user_for_testing(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);

        // Manually set user
        BranchContextManager::setCurrentUser($user);

        $currentUser = BranchContextManager::getCurrentUser();

        $this->assertNotNull($currentUser);
        $this->assertEquals($user->id, $currentUser->id);
    }

    public function test_handles_user_without_branches_relationship(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);

        $this->actingAs($user);

        $branchIds = BranchContextManager::getAccessibleBranchIds();

        $this->assertIsArray($branchIds);
        $this->assertContains($branch->id, $branchIds);
    }
}
