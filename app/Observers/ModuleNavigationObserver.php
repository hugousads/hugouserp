<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ModuleNavigation;
use Illuminate\Support\Facades\Cache;

/**
 * ModuleNavigationObserver
 *
 * Invalidates navigation caches when navigation items are modified.
 * This ensures sidebar and search navigation stay in sync with database.
 */
class ModuleNavigationObserver
{
    /**
     * Handle the ModuleNavigation "created" event.
     */
    public function created(ModuleNavigation $navigation): void
    {
        $this->invalidateNavigationCaches($navigation);
    }

    /**
     * Handle the ModuleNavigation "updated" event.
     */
    public function updated(ModuleNavigation $navigation): void
    {
        $this->invalidateNavigationCaches($navigation);
    }

    /**
     * Handle the ModuleNavigation "deleted" event.
     */
    public function deleted(ModuleNavigation $navigation): void
    {
        $this->invalidateNavigationCaches($navigation);
    }

    /**
     * Invalidate navigation-related caches.
     */
    protected function invalidateNavigationCaches(ModuleNavigation $navigation): void
    {
        // Try to use cache tags if available (Redis, Memcached)
        try {
            Cache::tags(['navigation'])->flush();
        } catch (\BadMethodCallException $e) {
            // Fallback: increment a version key to invalidate caches
            $version = (int) Cache::get('navigation:cache_version', 0);
            Cache::put('navigation:cache_version', $version + 1, 86400); // 24 hours
        }
    }
}
