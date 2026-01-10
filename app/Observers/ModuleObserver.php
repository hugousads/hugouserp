<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Module;
use Illuminate\Support\Facades\Cache;

/**
 * ModuleObserver
 *
 * Invalidates module-related caches when modules are created, updated, or deleted.
 * This ensures that navigation, module lists, and other cached data stay in sync.
 */
class ModuleObserver
{
    /**
     * Handle the Module "created" event.
     */
    public function created(Module $module): void
    {
        $this->invalidateModuleCaches($module);
    }

    /**
     * Handle the Module "updated" event.
     */
    public function updated(Module $module): void
    {
        $this->invalidateModuleCaches($module);
    }

    /**
     * Handle the Module "deleted" event.
     */
    public function deleted(Module $module): void
    {
        $this->invalidateModuleCaches($module);
    }

    /**
     * Invalidate all module-related caches.
     */
    protected function invalidateModuleCaches(Module $module): void
    {
        // Clear module type caches
        $types = ['data', 'functional', 'core'];
        foreach ($types as $type) {
            // Pattern: modules:type:{type}:b:{branchId}
            // We need to clear all branch variations
            Cache::forget("modules:type:{$type}:b:");
        }

        // Clear module policies cache
        Cache::forget("module_policies:{$module->id}:b:");

        // Try to use cache tags if available (Redis, Memcached)
        try {
            Cache::tags(['navigation'])->flush();
            Cache::tags(['modules'])->flush();
        } catch (\BadMethodCallException $e) {
            // Fallback: increment a version key to invalidate caches
            $version = (int) Cache::get('modules:cache_version', 0);
            Cache::put('modules:cache_version', $version + 1, 86400); // 24 hours
        }
    }
}
