<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\HREmployee;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\RentalContract;
use App\Models\Sale;
use App\Models\SearchHistory;
use App\Models\SearchIndex;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class GlobalSearchService
{
    /**
     * Searchable models configuration.
     */
    private const SEARCHABLE_MODELS = [
        'products' => [
            'model' => Product::class,
            'title' => ['name', 'sku'],
            'content' => ['description', 'barcode'],
            'icon' => '📦',
            'route' => 'products.show',
            'module' => 'inventory',
        ],
        'customers' => [
            'model' => Customer::class,
            'title' => ['name', 'email'],
            'content' => ['phone', 'address'],
            'icon' => '👤',
            'route' => 'customers.show',
            'module' => 'customers',
        ],
        'suppliers' => [
            'model' => Supplier::class,
            'title' => ['name', 'email'],
            'content' => ['phone', 'address'],
            'icon' => '🏭',
            'route' => 'suppliers.show',
            'module' => 'suppliers',
        ],
        'sales' => [
            'model' => Sale::class,
            'title' => ['code'],
            'content' => ['notes'],
            'icon' => '💵',
            'route' => 'sales.show',
            'module' => 'sales',
        ],
        'purchases' => [
            'model' => Purchase::class,
            'title' => ['code'],
            'content' => ['notes'],
            'icon' => '🛒',
            'route' => 'purchases.show',
            'module' => 'purchases',
        ],
        'rental_contracts' => [
            'model' => RentalContract::class,
            'title' => ['contract_number'],
            'content' => ['notes'],
            'icon' => '📋',
            'route' => 'rentals.contracts.show',
            'module' => 'rentals',
        ],
        'employees' => [
            'model' => HREmployee::class,
            'title' => ['first_name', 'last_name', 'email'],
            'content' => ['phone', 'position'],
            'icon' => '👨‍💼',
            'route' => 'hrm.employees.show',
            'module' => 'hrm',
        ],
    ];

    private const MODULE_PERMISSIONS = [
        'inventory' => 'inventory.products.view',
        'customers' => 'customers.view',
        'suppliers' => 'suppliers.view',
        'sales' => 'sales.view',
        'purchases' => 'purchases.view',
        'rentals' => 'rental.contracts.view',
        'hrm' => 'hrm.employees.view',
    ];

    /**
     * Perform global search.
     */
    public function search(string $query, User $user, ?int $branchId = null, ?string $module = null): array
    {
        if (strlen($query) < 2) {
            return ['results' => [], 'count' => 0];
        }

        $resolvedBranchId = $this->resolveBranchId($user, $branchId);
        $authorizedModules = $this->authorizedModulesForUser($user, $module);

        // Log search if user provided
        $this->logSearch($user->id, $query, $module);

        // Search in index
        $results = SearchIndex::search($query, $resolvedBranchId, $authorizedModules, 50);

        // Group by module
        $grouped = collect($results)->groupBy('module')->map(function ($items) {
            return $items->take(10)->toArray();
        })->toArray();

        return [
            'results' => $results,
            'grouped' => $grouped,
            'count' => count($results),
        ];
    }

    /**
     * Index a model instance.
     */
    public function indexModel($model): void
    {
        $config = $this->getModelConfig($model);

        if (! $config) {
            return;
        }

        $title = $this->extractFields($model, $config['title']);
        $content = $this->extractFields($model, $config['content']);

        SearchIndex::updateOrCreate(
            [
                'searchable_type' => get_class($model),
                'searchable_id' => $model->id,
            ],
            [
                'branch_id' => $model->branch_id ?? config('app.default_branch_id', 1),
                'title' => $title,
                'content' => $content,
                'module' => $config['module'],
                'icon' => $config['icon'],
                'url' => $this->generateUrl($model, $config['route']),
                'metadata' => $this->extractMetadata($model),
                'indexed_at' => now(),
            ]
        );
    }

    /**
     * Remove model from index.
     */
    public function removeFromIndex($model): void
    {
        SearchIndex::where('searchable_type', get_class($model))
            ->where('searchable_id', $model->id)
            ->delete();
    }

    /**
     * Reindex all searchable models.
     */
    public function reindexAll(?int $branchId = null): int
    {
        $indexed = 0;

        foreach (self::SEARCHABLE_MODELS as $config) {
            $query = $config['model']::query();

            if ($branchId && method_exists($config['model'], 'branch')) {
                $query->where('branch_id', $branchId);
            }

            $query->chunk(100, function ($models) use (&$indexed) {
                foreach ($models as $model) {
                    $this->indexModel($model);
                    $indexed++;
                }
            });
        }

        return $indexed;
    }

    /**
     * Get recent searches for user.
     */
    public function getRecentSearches(int $userId, int $limit = 10): array
    {
        return SearchHistory::getRecentSearches($userId, $limit);
    }

    /**
     * Get popular searches.
     */
    public function getPopularSearches(int $limit = 10): array
    {
        return SearchHistory::getPopularSearches($limit);
    }

    /**
     * Clear search history for user.
     */
    public function clearHistory(int $userId): void
    {
        SearchHistory::where('user_id', $userId)->delete();
    }

    /**
     * Get available modules for filtering.
     */
    public function getAvailableModules(User $user): array
    {
        return $this->authorizedModulesForUser($user);
    }

    /**
     * Log a search query.
     */
    private function logSearch(int $userId, string $query, ?string $module): void
    {
        SearchHistory::create([
            'user_id' => $userId,
            'query' => $query,
            'module' => $module,
            'results_count' => 0, // Updated after search completes
        ]);
    }

    /**
     * Get model configuration.
     */
    private function getModelConfig($model): ?array
    {
        $class = get_class($model);

        foreach (self::SEARCHABLE_MODELS as $config) {
            if ($config['model'] === $class) {
                return $config;
            }
        }

        return null;
    }

    /**
     * Extract fields from model.
     */
    private function extractFields($model, array $fields): string
    {
        $values = [];

        foreach ($fields as $field) {
            if (isset($model->$field) && ! empty($model->$field)) {
                $values[] = $model->$field;
            }
        }

        return implode(' ', $values);
    }

    /**
     * Generate URL for model.
     */
    private function generateUrl($model, string $route): string
    {
        try {
            return route($route, $model->id);
        } catch (\Exception $e) {
            return '#';
        }
    }

    /**
     * Extract metadata from model.
     */
    private function extractMetadata($model): array
    {
        $metadata = [
            'id' => $model->id,
        ];

        // Add common fields if available
        if (isset($model->status)) {
            $metadata['status'] = $model->status;
        }

        if (isset($model->created_at)) {
            $metadata['created_at'] = $model->created_at->toISOString();
        }

        return $metadata;
    }

    private function resolveBranchId(User $user, ?int $branchId): int
    {
        $resolvedBranch = $branchId ?? $user->current_branch_id ?? $user->branch_id;

        if (! $resolvedBranch) {
            throw new AuthorizationException('Branch context is required for search.');
        }

        return (int) $resolvedBranch;
    }

    /**
     * @return array<string>
     */
    private function authorizedModulesForUser(User $user, ?string $module = null): array
    {
        $modules = [];

        foreach (self::MODULE_PERMISSIONS as $moduleKey => $permission) {
            if ($user->can($permission)) {
                $modules[] = $moduleKey;
            }
        }

        if ($module) {
            if (! in_array($module, $modules, true)) {
                throw new AuthorizationException('You are not allowed to search this module.');
            }

            return [$module];
        }

        if (empty($modules)) {
            throw new AuthorizationException('You are not allowed to perform searches.');
        }

        return $modules;
    }
}
