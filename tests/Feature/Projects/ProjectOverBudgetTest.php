<?php

declare(strict_types=1);

namespace Tests\Feature\Projects;

use App\Models\Branch;
use App\Models\Project;
use App\Models\ProjectExpense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectOverBudgetTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create(['name' => 'Test Branch', 'code' => 'TB001']);
        $this->user = User::factory()->create();
    }

    public function test_project_with_approved_expenses_and_no_time_logs_is_over_budget(): void
    {
        // Create a project with a budget of 1000
        $project = Project::create([
            'name' => 'Test Project',
            'code' => 'PROJ001',
            'description' => 'Test project to verify over-budget detection',
            'start_date' => now(),
            'status' => 'active',
            'branch_id' => $this->branch->id,
            'budget' => 1000.00,
        ]);

        // Add approved expenses totaling 1500 (over budget)
        ProjectExpense::create([
            'project_id' => $project->id,
            'description' => 'Expense 1',
            'category' => 'materials',
            'amount' => 800.00,
            'status' => 'approved',
            'expense_date' => now(),
            'user_id' => $this->user->id,
        ]);

        ProjectExpense::create([
            'project_id' => $project->id,
            'description' => 'Expense 2',
            'category' => 'materials',
            'amount' => 700.00,
            'status' => 'approved',
            'expense_date' => now(),
            'user_id' => $this->user->id,
        ]);

        // Do NOT create any time logs - this tests the COALESCE fix

        // Query using the overBudget scope
        $overBudgetProjects = Project::overBudget()->get();

        // Assert that the project is detected as over budget
        $this->assertCount(1, $overBudgetProjects);
        $this->assertEquals($project->id, $overBudgetProjects->first()->id);
    }

    public function test_project_with_no_expenses_and_no_time_logs_is_not_over_budget(): void
    {
        // Create a project with a budget
        $project = Project::create([
            'name' => 'Test Project',
            'code' => 'PROJ002',
            'description' => 'Test project with no expenses or time logs',
            'start_date' => now(),
            'status' => 'active',
            'branch_id' => $this->branch->id,
            'budget' => 1000.00,
        ]);

        // Query using the overBudget scope
        $overBudgetProjects = Project::overBudget()->get();

        // Assert that the project is NOT detected as over budget
        $this->assertCount(0, $overBudgetProjects);
    }

    public function test_project_with_pending_expenses_is_not_over_budget(): void
    {
        // Create a project with a budget of 1000
        $project = Project::create([
            'name' => 'Test Project',
            'code' => 'PROJ003',
            'description' => 'Test project with pending expenses',
            'start_date' => now(),
            'status' => 'active',
            'branch_id' => $this->branch->id,
            'budget' => 1000.00,
        ]);

        // Add pending expenses totaling 1500 (would be over budget if approved)
        ProjectExpense::create([
            'project_id' => $project->id,
            'description' => 'Pending Expense',
            'category' => 'materials',
            'amount' => 1500.00,
            'status' => 'pending',
            'expense_date' => now(),
            'user_id' => $this->user->id,
        ]);

        // Query using the overBudget scope
        $overBudgetProjects = Project::overBudget()->get();

        // Assert that the project is NOT detected as over budget (only approved expenses count)
        $this->assertCount(0, $overBudgetProjects);
    }
}
