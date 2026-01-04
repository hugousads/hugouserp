<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

/**
 * Livewire 4 Lazy Loading Trait
 * 
 * Provides:
 * - Skeleton loading states
 * - Progressive data loading
 * - Optimized queries with caching
 * - Mobile-friendly pagination
 */
trait WithLazyLoading
{
    public bool $isLazyLoaded = false;
    public bool $showLoadingState = true;
    public int $skeletonCount = 5;
    
    /**
     * Initialize lazy loading
     */
    public function bootWithLazyLoading(): void
    {
        // Check if we should enable skeleton loading
        $this->showLoadingState = config('settings.advanced.lazy_load_components', true);
    }

    /**
     * Mark component as loaded (call after initial data fetch)
     */
    public function markAsLoaded(): void
    {
        $this->isLazyLoaded = true;
    }

    /**
     * Get skeleton placeholder count based on viewport
     */
    #[Computed]
    public function placeholderCount(): int
    {
        return $this->skeletonCount;
    }

    /**
     * Execute query with caching for better performance
     */
    protected function cachedQuery(Builder $query, string $cacheKey, int $ttl = 300): mixed
    {
        if (!config('settings.advanced.cache_ttl', 0)) {
            return $query->get();
        }

        $fullCacheKey = $this->buildCacheKey($cacheKey);
        
        return Cache::remember($fullCacheKey, $ttl, fn() => $query->get());
    }

    /**
     * Execute paginated query with caching
     */
    protected function cachedPaginate(Builder $query, int $perPage, string $cacheKey, int $ttl = 60): LengthAwarePaginator
    {
        // For paginated results, we typically don't cache as the page changes
        // but we can cache the total count for faster pagination
        $totalCacheKey = $this->buildCacheKey($cacheKey . '_total');
        
        $total = Cache::remember($totalCacheKey, $ttl, fn() => $query->count());
        
        return $query->paginate($perPage);
    }

    /**
     * Build a cache key with user/branch context
     */
    protected function buildCacheKey(string $key): string
    {
        $userId = auth()->id() ?? 'guest';
        $branchId = auth()->user()?->branch_id ?? 'all';
        
        return "lazy_{$key}_{$userId}_{$branchId}";
    }

    /**
     * Clear cached data for this component
     */
    protected function clearLazyCache(string $cacheKey): void
    {
        Cache::forget($this->buildCacheKey($cacheKey));
        Cache::forget($this->buildCacheKey($cacheKey . '_total'));
    }

    /**
     * Get loading state CSS classes
     */
    #[Computed]
    public function loadingClasses(): string
    {
        return $this->showLoadingState && !$this->isLazyLoaded 
            ? 'animate-pulse opacity-50' 
            : '';
    }

    /**
     * Handle refresh events
     */
    #[On('refresh-data')]
    public function refreshLazyData(): void
    {
        $this->isLazyLoaded = false;
        // Subclasses should override to clear specific caches
    }
}
