<?php

declare(strict_types=1);

namespace Tests\Feature\Workflow;

use App\Models\Branch;
use App\Models\User;
use App\Models\WorkflowApproval;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SelfApprovalPreventionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $approver;
    protected Branch $branch;
    protected WorkflowService $workflowService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create([
            'name' => 'Test Branch',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'email' => 'user@test.com',
            'password' => bcrypt('password'),
            'branch_id' => $this->branch->id,
        ]);

        $this->approver = User::factory()->create([
            'email' => 'approver@test.com',
            'password' => bcrypt('password'),
            'branch_id' => $this->branch->id,
        ]);

        $this->workflowService = app(WorkflowService::class);
    }

    public function test_user_cannot_approve_their_own_workflow(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Test Workflow',
            'module_name' => 'purchases',
            'entity_type' => 'purchase',
            'is_active' => true,
            'is_mandatory' => true,
            'stages' => [
                ['name' => 'Manager Approval', 'order' => 1, 'approver_role' => 'manager'],
            ],
        ]);

        $instance = WorkflowInstance::create([
            'workflow_definition_id' => $workflow->id,
            'branch_id' => $this->branch->id,
            'entity_type' => 'purchase',
            'entity_id' => 1,
            'current_stage' => 'Manager Approval',
            'status' => 'pending',
            'initiated_by' => $this->user->id,
            'initiated_at' => now(),
        ]);

        $approval = WorkflowApproval::create([
            'workflow_instance_id' => $instance->id,
            'stage_name' => 'Manager Approval',
            'stage_order' => 1,
            'approver_id' => $this->user->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('You cannot approve your own request');

        $this->workflowService->approve($approval, $this->user->id, 'Approving my own request');
    }

    public function test_different_user_can_approve_workflow(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Test Workflow',
            'module_name' => 'purchases',
            'entity_type' => 'purchase',
            'is_active' => true,
            'is_mandatory' => true,
            'stages' => [
                ['name' => 'Manager Approval', 'order' => 1, 'approver_role' => 'manager'],
            ],
        ]);

        $instance = WorkflowInstance::create([
            'workflow_definition_id' => $workflow->id,
            'branch_id' => $this->branch->id,
            'entity_type' => 'purchase',
            'entity_id' => 1,
            'current_stage' => 'Manager Approval',
            'status' => 'pending',
            'initiated_by' => $this->user->id,
            'initiated_at' => now(),
        ]);

        $approval = WorkflowApproval::create([
            'workflow_instance_id' => $instance->id,
            'stage_name' => 'Manager Approval',
            'stage_order' => 1,
            'approver_id' => $this->approver->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $result = $this->workflowService->approve($approval, $this->approver->id, 'Approved');

        $this->assertNotNull($result);
        $approval->refresh();
        $this->assertEquals('approved', $approval->status);
    }
}
