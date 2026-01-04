<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Trait for optimizing database queries in Eloquent models.
 * 
 * Provides methods for:
 * - Query caching
 * - Eager loading optimization
 * - Chunk processing for large datasets
 * - Query performance monitoring
 */
trait OptimizedQueries
{
    /**
     * Default cache TTL in seconds (5 minutes)
     */
    protected int $defaultCacheTtl = 300;

    /**
     * Get cached query results.
     *
     * @param  string  $key  Cache key
     * @param  callable  $callback  Query callback
     * @param  int|null  $ttl  Cache TTL in seconds
     * @return mixed
     */
    public static function cached(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $instance = new static;
        $ttl = $ttl ?? $instance->defaultCacheTtl;
        
        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Get cached query results with tags.
     *
     * @param  array  $tags  Cache tags
     * @param  string  $key  Cache key
     * @param  callable  $callback  Query callback
     * @param  int|null  $ttl  Cache TTL in seconds
     * @return mixed
     */
    public static function cachedWithTags(array $tags, string $key, callable $callback, ?int $ttl = null): mixed
    {
        $instance = new static;
        $ttl = $ttl ?? $instance->defaultCacheTtl;

        if (config('cache.default') === 'file' || config('cache.default') === 'database') {
            // Tags not supported, fall back to regular cache
            return Cache::remember($key, $ttl, $callback);
        }

        return Cache::tags($tags)->remember($key, $ttl, $callback);
    }

    /**
     * Clear model cache.
     */
    public static function clearCache(?array $tags = null): void
    {
        $modelTag = class_basename(static::class);
        
        if ($tags === null) {
            $tags = [$modelTag];
        }

        if (config('cache.default') !== 'file' && config('cache.default') !== 'database') {
            Cache::tags($tags)->flush();
        }
    }

    /**
     * Scope for optimized pagination with cursor.
     */
    public function scopeOptimizedPaginate(Builder $query, int $perPage = 15): \Illuminate\Contracts\Pagination\CursorPaginator
    {
        return $query->cursorPaginate($perPage);
    }

    /**
     * Scope for selecting only indexed columns.
     */
    public function scopeSelectIndexed(Builder $query, array $columns = ['id']): Builder
    {
        return $query->select($columns);
    }

    /**
     * Process large datasets in chunks with progress callback.
     *
     * @param  int  $chunkSize  Number of records per chunk
     * @param  callable  $callback  Callback for each record
     * @param  callable|null  $progressCallback  Progress callback
     * @return bool
     */
    public static function processInChunks(int $chunkSize, callable $callback, ?callable $progressCallback = null): bool
    {
        $processed = 0;
        $total = static::count();

        return static::query()->chunkById($chunkSize, function ($records) use ($callback, $progressCallback, &$processed, $total) {
            foreach ($records as $record) {
                $callback($record);
                $processed++;
                
                if ($progressCallback) {
                    $progressCallback($processed, $total);
                }
            }
            
            return true;
        });
    }

    /**
     * Get query with performance hints for MySQL 8.x.
     */
    public function scopeWithPerformanceHints(Builder $query): Builder
    {
        // Use straight join for predictable join order
        return $query->from(DB::raw('/*+ SET_VAR(optimizer_switch="index_merge_intersection=on") */ ' . $this->getTable()));
    }

    /**
     * Scope for read-only queries (uses replica if configured).
     */
    public function scopeReadOnly(Builder $query): Builder
    {
        return $query->useReadPdo();
    }

    /**
     * Get model with all eager-loaded relations defined in $with.
     */
    public static function withAllRelations(): Builder
    {
        $instance = new static;
        $relations = property_exists($instance, 'with') ? $instance->with : [];
        
        return static::with($relations);
    }

    /**
     * Count with caching.
     */
    public static function cachedCount(?string $cacheKey = null, ?int $ttl = null): int
    {
        $key = $cacheKey ?? 'count_' . class_basename(static::class);
        
        return static::cached($key, fn() => static::count(), $ttl);
    }

    /**
     * Check if model uses soft deletes and apply appropriate scope.
     */
    public function scopeActiveOnly(Builder $query): Builder
    {
        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive(static::class))) {
            return $query->whereNull('deleted_at');
        }
        
        if ($this->hasAttribute('is_active')) {
            return $query->where('is_active', true);
        }
        
        return $query;
    }

    /**
     * Helper to check if model has an attribute.
     */
    protected function hasAttribute(string $attribute): bool
    {
        return in_array($attribute, $this->fillable) || 
               array_key_exists($attribute, $this->casts ?? []);
    }
}
