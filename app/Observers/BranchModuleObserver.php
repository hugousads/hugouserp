<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\BranchModule;
use Illuminate\Support\Facades\Cache;

/**
 * BranchModuleObserver
 *
 * Invalidates caches when branch-module relationships change.
 * This ensures sidebar navigation, module access, and other cached data stay current.
 */
class BranchModuleObserver
{
    /**
     * Handle the BranchModule "created" event.
     */
    public function created(BranchModule $branchModule): void
    {
        $this->invalidateBranchCaches($branchModule);
    }

    /**
     * Handle the BranchModule "updated" event.
     */
    public function updated(BranchModule $branchModule): void
    {
        $this->invalidateBranchCaches($branchModule);
    }

    /**
     * Handle the BranchModule "deleted" event.
     */
    public function deleted(BranchModule $branchModule): void
    {
        $this->invalidateBranchCaches($branchModule);
    }

    /**
     * Invalidate caches related to branch modules.
     */
    protected function invalidateBranchCaches(BranchModule $branchModule): void
    {
        $branchId = $branchModule->branch_id;

        // Clear branch-specific module cache
        Cache::forget("modules:b:{$branchId}");

        // Clear module type caches for this branch
        $types = ['data', 'functional', 'core'];
        foreach ($types as $type) {
            Cache::forget("modules:type:{$type}:b:{$branchId}");
        }

        // Try to use cache tags if available (Redis, Memcached)
        try {
            Cache::tags(["nav:branch:{$branchId}"])->flush();
            Cache::tags(['navigation'])->flush();
        } catch (\BadMethodCallException $e) {
            // Fallback: increment a version key to invalidate caches
            $version = (int) Cache::get('modules:cache_version', 0);
            Cache::put('modules:cache_version', $version + 1, 86400); // 24 hours
        }
    }
}
