<?php

declare(strict_types=1);

namespace Tests\Feature\GlobalSearch;

use App\Livewire\Components\GlobalSearch;
use App\Models\User;
use App\Services\GlobalSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ModuleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_perform_search_blocks_unauthorized_module(): void
    {
        $this->withExceptionHandling();

        $user = User::factory()->create();

        app()->instance(GlobalSearchService::class, new class
        {
            public function search(string $query, User $user, ?int $branchId, ?string $module = null): array
            {
                return ['results' => [], 'count' => 0, 'grouped' => []];
            }

            public function getAvailableModules(User $user): array
            {
                return ['sales' => 'Sales'];
            }

            public function getRecentSearches(): array
            {
                return [];
            }

            public function clearHistory(): void {}
        });

        $this->actingAs($user);

        $component = app(GlobalSearch::class);
        $component->query = 'invoice';
        $component->selectedModule = 'forbidden';

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $component->performSearch();
    }
}
