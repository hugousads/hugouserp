<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Branch;
use App\Models\BranchAdmin;
use App\Models\User;
use App\Services\BranchContextManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Integration test to verify infinite recursion is fixed
 */
class BranchScopeRecursionFixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure roles exist
        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Branch Manager', 'guard_name' => 'web']);
        
        BranchContextManager::clearCache();
    }

    protected function tearDown(): void
    {
        BranchContextManager::clearCache();
        parent::tearDown();
    }

    /**
     * This test verifies the main issue is fixed:
     * Previously, authenticating a user who has BranchAdmin records
     * would cause infinite recursion when BranchScope tried to load
     * the user's branches relationship.
     */
    public function test_authentication_does_not_cause_infinite_recursion(): void
    {
        // Create a branch
        $branch = Branch::factory()->create([
            'name' => 'Test Branch',
            'code' => 'TB001',
        ]);

        // Create a user
        $user = User::factory()->create([
            'branch_id' => $branch->id,
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);

        // Assign role
        $user->assignRole('Branch Manager');

        // Create BranchAdmin record - this is what triggers the recursion
        BranchAdmin::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'is_active' => true,
            'can_manage_users' => true,
            'can_view_reports' => true,
        ]);

        // The critical test: actingAs should not cause infinite recursion
        // Before the fix, this would fail with "Maximum call stack size exceeded"
        $this->actingAs($user);

        // Verify user is authenticated
        $this->assertAuthenticated();
        $this->assertEquals($user->id, auth()->id());

        // Verify we can call methods that would previously trigger recursion
        $isBranchAdmin = $user->isBranchAdmin();
        $this->assertTrue($isBranchAdmin);

        // Verify BranchContextManager works
        $currentUser = BranchContextManager::getCurrentUser();
        $this->assertNotNull($currentUser);
        $this->assertEquals($user->id, $currentUser->id);
    }

    public function test_branch_admin_queries_do_not_apply_branch_scope(): void
    {
        $branch1 = Branch::factory()->create(['name' => 'Branch 1']);
        $branch2 = Branch::factory()->create(['name' => 'Branch 2']);

        $user = User::factory()->create(['branch_id' => $branch1->id]);

        // Create BranchAdmin records in both branches
        $admin1 = BranchAdmin::create([
            'user_id' => $user->id,
            'branch_id' => $branch1->id,
            'is_active' => true,
        ]);

        $admin2 = BranchAdmin::create([
            'user_id' => $user->id,
            'branch_id' => $branch2->id,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        // BranchAdmin should NOT be filtered by BranchScope
        // User should see all their admin records regardless of current branch context
        $adminRecords = BranchAdmin::where('user_id', $user->id)->get();

        $this->assertCount(2, $adminRecords);
        $this->assertTrue($adminRecords->contains('id', $admin1->id));
        $this->assertTrue($adminRecords->contains('id', $admin2->id));
    }

    public function test_loading_user_branches_relationship_does_not_cause_recursion(): void
    {
        $branch1 = Branch::factory()->create();
        $branch2 = Branch::factory()->create();

        $user = User::factory()->create(['branch_id' => $branch1->id]);
        $user->branches()->attach($branch2->id);

        $this->actingAs($user);

        // Loading branches relationship should not cause recursion
        $user->load('branches');

        $this->assertTrue($user->relationLoaded('branches'));
        $this->assertCount(1, $user->branches);
    }

    public function test_branch_context_manager_prevents_recursion_flag(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);

        $this->actingAs($user);

        // Initially, we're not resolving auth
        $this->assertFalse(BranchContextManager::isResolvingAuth());

        // Get user should work without setting the flag externally
        $currentUser = BranchContextManager::getCurrentUser();
        $this->assertNotNull($currentUser);

        // After getting user, flag should be false again
        $this->assertFalse(BranchContextManager::isResolvingAuth());
    }
}
