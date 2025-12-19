<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Module;
use App\Models\ModuleNavigation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Service for managing dynamic navigation based on modules
 */
class ModuleNavigationService
{
    /**
     * Get navigation structure for a specific user and branch
     */
    public function getNavigationForUser(User $user, ?int $branchId = null): array
    {
        $cacheKey = sprintf(
            'nav:user:%d:branch:%s:locale:%s',
            $user->id,
            $branchId ?? 'null',
            app()->getLocale()
        );

        return Cache::remember($cacheKey, 600, function () use ($user, $branchId) {
            return $this->buildNavigationTree($user, $branchId);
        });
    }

    /**
     * Build navigation tree from database
     */
    protected function buildNavigationTree(User $user, ?int $branchId = null): array
    {
        // Get all active root navigation items ordered by sort_order
        $rootItems = ModuleNavigation::query()
            ->with(['module', 'children' => function ($query) {
                $query->active()->ordered();
            }])
            ->active()
            ->rootItems()
            ->ordered()
            ->get();

        $navigation = [];

        foreach ($rootItems as $item) {
            if ($item->userHasAccess($user, $branchId)) {
                $navItem = $this->buildNavigationItem($item, $user, $branchId);
                if ($navItem) {
                    $navigation[] = $navItem;
                }
            }
        }

        return $navigation;
    }

    /**
     * Build a single navigation item with its children
     */
    protected function buildNavigationItem(ModuleNavigation $item, User $user, ?int $branchId = null): ?array
    {
        $navItem = [
            'id' => $item->id,
            'key' => $item->nav_key,
            'label' => $item->localized_label,
            'route' => $item->route_name,
            'icon' => $item->icon,
            'module_id' => $item->module_id,
            'module_key' => $item->module?->key,
            'children' => [],
        ];

        // Process children recursively
        foreach ($item->children as $child) {
            if ($child->userHasAccess($user, $branchId)) {
                $childItem = $this->buildNavigationItem($child, $user, $branchId);
                if ($childItem) {
                    $navItem['children'][] = $childItem;
                }
            }
        }

        return $navItem;
    }

    /**
     * Get navigation grouped by category (for different dashboard types)
     */
    public function getNavigationByCategory(User $user, ?int $branchId = null, ?string $category = null): array
    {
        $navigation = $this->getNavigationForUser($user, $branchId);

        // Group by module category if needed
        if ($category) {
            return array_filter($navigation, function ($item) use ($category) {
                return isset($item['module_key']) &&
                       $this->getModuleCategory($item['module_key']) === $category;
            });
        }

        return $navigation;
    }

    /**
     * Get module category for grouping
     */
    protected function getModuleCategory(string $moduleKey): string
    {
        $categories = [
            'dashboard' => ['dashboard'],
            'sales' => ['pos', 'sales', 'customers'],
            'inventory' => ['inventory', 'warehouse', 'products'],
            'purchases' => ['purchases', 'suppliers'],
            'financial' => ['accounting', 'expenses', 'income'],
            'hr' => ['hrm', 'payroll', 'attendance'],
            'rental' => ['rental', 'properties', 'contracts'],
            'reports' => ['reports', 'analytics'],
            'admin' => ['settings', 'users', 'roles', 'modules', 'branches'],
        ];

        foreach ($categories as $category => $keys) {
            if (in_array($moduleKey, $keys)) {
                return $category;
            }
        }

        return 'other';
    }

    /**
     * Get quick actions for user
     */
    public function getQuickActionsForUser(User $user, ?int $branchId = null): array
    {
        $quickActions = [];

        // Define quick actions based on permissions
        $actionDefinitions = [
            [
                'key' => 'new_sale',
                'label' => __('New Sale'),
                'label_ar' => 'بيع جديد',
                'route' => 'pos.terminal',
                'permission' => 'sales.create',
                'icon' => '🧾',
                'color' => 'emerald',
            ],
            [
                'key' => 'new_product',
                'label' => __('New Product'),
                'label_ar' => 'منتج جديد',
                'route' => 'inventory.products.create',
                'permission' => 'inventory.products.create',
                'icon' => '📦',
                'color' => 'blue',
            ],
            [
                'key' => 'new_purchase',
                'label' => __('New Purchase'),
                'label_ar' => 'شراء جديد',
                'route' => 'purchases.create',
                'permission' => 'purchases.create',
                'icon' => '🛒',
                'color' => 'purple',
            ],
            [
                'key' => 'new_customer',
                'label' => __('New Customer'),
                'label_ar' => 'عميل جديد',
                'route' => 'customers.create',
                'permission' => 'customers.create',
                'icon' => '👤',
                'color' => 'cyan',
            ],
            [
                'key' => 'new_expense',
                'label' => __('New Expense'),
                'label_ar' => 'مصروف جديد',
                'route' => 'expenses.create',
                'permission' => 'expenses.create',
                'icon' => '💳',
                'color' => 'red',
            ],
        ];

        foreach ($actionDefinitions as $action) {
            if ($user->can($action['permission'])) {
                $quickActions[] = [
                    'key' => $action['key'],
                    'label' => app()->getLocale() === 'ar' ? $action['label_ar'] : $action['label'],
                    'route' => $action['route'],
                    'icon' => $action['icon'],
                    'color' => $action['color'],
                ];
            }
        }

        return $quickActions;
    }

    /**
     * Clear navigation cache for a user
     */
    public function clearNavigationCache(User $user, ?array $branchIds = null): void
    {
        $locales = ['ar', 'en'];

        $branches = collect($branchIds ?? [])
            ->when($branchIds === null, function ($collection) use ($user) {
                $userBranchIds = $user->branches()->pluck('branches.id')->all();

                if ($user->branch_id) {
                    $userBranchIds[] = $user->branch_id;
                }

                return $collection->merge($userBranchIds);
            })
            ->push(null)
            ->unique();

        foreach ($locales as $locale) {
            foreach ($branches as $branchId) {
                Cache::forget(
                    sprintf('nav:user:%d:branch:%s:locale:%s', $user->id, $branchId ?? 'null', $locale)
                );
            }
        }
    }

    /**
     * Sync navigation from module configuration
     * This method can be called to regenerate navigation from module definitions
     */
    public function syncNavigationFromModules(): void
    {
        $modules = Module::where('is_active', true)->get();

        foreach ($modules as $module) {
            $this->syncModuleNavigation($module);
        }
    }

    /**
     * Sync navigation for a specific module
     */
    protected function syncModuleNavigation(Module $module): void
    {
        // This would be implemented to create/update navigation items
        // based on module configuration
        // For now, this is a placeholder for future implementation
    }

    /**
     * Get navigation structure for specific role type
     */
    public function getNavigationForRole(string $roleType, User $user, ?int $branchId = null): array
    {
        $navigation = $this->getNavigationForUser($user, $branchId);

        // Filter based on role type
        switch ($roleType) {
            case 'admin':
                // Admin sees everything they have permission for
                return $navigation;

            case 'branch_manager':
                // Branch managers see operational items
                return $this->filterNavigationByCategories($navigation, [
                    'dashboard', 'sales', 'inventory', 'purchases', 'hr', 'reports',
                ]);

            case 'sales_user':
                // Sales users see only sales-related items
                return $this->filterNavigationByCategories($navigation, [
                    'dashboard', 'sales', 'inventory',
                ]);

            case 'warehouse_user':
                // Warehouse users see inventory-related items
                return $this->filterNavigationByCategories($navigation, [
                    'dashboard', 'inventory', 'purchases',
                ]);

            default:
                return $navigation;
        }
    }

    /**
     * Filter navigation by categories
     */
    protected function filterNavigationByCategories(array $navigation, array $allowedCategories): array
    {
        return array_filter($navigation, function ($item) use ($allowedCategories) {
            if (! isset($item['module_key'])) {
                return false;
            }
            $category = $this->getModuleCategory($item['module_key']);

            return in_array($category, $allowedCategories);
        });
    }
}
