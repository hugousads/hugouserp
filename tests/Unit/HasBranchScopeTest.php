<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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

        $projectInSecondary = Project::create([
            'branch_id' => $secondaryBranch->id,
            'code' => 'PRJ-' . Str::random(5),
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
}
