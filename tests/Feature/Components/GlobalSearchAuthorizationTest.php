<?php

declare(strict_types=1);

namespace Tests\Feature\Components;

use App\Livewire\Components\GlobalSearch;
use App\Models\Branch;
use App\Models\SearchIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GlobalSearchAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_results_are_filtered_by_user_permissions(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);

        Permission::findOrCreate('customers.view');
        Permission::findOrCreate('sales.view');

        $user->givePermissionTo('customers.view');

        SearchIndex::create([
            'branch_id' => $branch->id,
            'searchable_type' => User::class,
            'searchable_id' => $user->id,
            'title' => 'Shared Keyword',
            'content' => 'Shared Keyword content',
            'module' => 'sales',
            'icon' => '💵',
            'url' => '/sales/1',
            'metadata' => [],
            'indexed_at' => now(),
        ]);

        SearchIndex::create([
            'branch_id' => $branch->id,
            'searchable_type' => User::class,
            'searchable_id' => $user->id,
            'title' => 'Shared Keyword',
            'content' => 'Another Shared Keyword',
            'module' => 'customers',
            'icon' => '👤',
            'url' => '/customers/1',
            'metadata' => [],
            'indexed_at' => now(),
        ]);

        $component = Livewire::actingAs($user)
            ->test(GlobalSearch::class)
            ->set('query', 'Shared')
            ->call('performSearch');

        $results = $component->get('results');

        $this->assertCount(1, $results);
        $this->assertSame('customers', $results[0]['module']);
    }

    public function test_branch_context_is_enforced_when_missing_current_branch(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        $user = User::factory()->create([
            'branch_id' => $branchA->id,
        ]);

        Permission::findOrCreate('customers.view');
        $user->givePermissionTo('customers.view');

        foreach ([$branchA->id, $branchB->id] as $idx => $branchId) {
            SearchIndex::create([
                'branch_id' => $branchId,
                'searchable_type' => User::class,
                'searchable_id' => $user->id,
                'title' => 'Alpha Entry',
                'content' => 'Alpha Entry',
                'module' => 'customers',
                'icon' => '👤',
                'url' => '/customers/'.$idx,
                'metadata' => [],
                'indexed_at' => now(),
            ]);
        }

        $component = Livewire::actingAs($user)
            ->test(GlobalSearch::class)
            ->set('query', 'Alpha')
            ->call('performSearch');

        $results = $component->get('results');

        $this->assertCount(1, $results);
        $this->assertSame($branchA->id, $results[0]['branch_id']);
    }
}
