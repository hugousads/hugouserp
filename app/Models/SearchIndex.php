<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SearchIndex extends BaseModel
{
    use HasFactory;

    protected $table = 'search_index';

    protected $fillable = [
        'branch_id',
        'searchable_type',
        'searchable_id',
        'title',
        'content',
        'module',
        'icon',
        'url',
        'metadata',
        'indexed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'indexed_at' => 'datetime',
    ];

    /**
     * Get the branch.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the searchable model.
     */
    public function searchable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Search across all indexed content.
     */
    public static function search(string $query, int $branchId, array|string|null $module = null, int $limit = 20): array
    {
        $builder = static::query()->where('branch_id', $branchId);

        if ($module) {
            if (is_array($module)) {
                if (empty($module)) {
                    return [];
                }

                $builder->whereIn('module', $module);
            } else {
                $builder->where('module', $module);
            }
        }

        // Use full-text search if available
        if (static::hasFullTextIndex()) {
            $builder->whereRaw(
                'MATCH(title, content) AGAINST(? IN BOOLEAN MODE)',
                [$query]
            );
        } else {
            // Fallback to LIKE search
            $builder->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('content', 'like', "%{$query}%");
            });
        }

        return $builder->orderBy('indexed_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Check if full-text index exists.
     */
    private static function hasFullTextIndex(): bool
    {
        try {
            $connection = config('database.default');
            $driver = config("database.connections.{$connection}.driver");

            return in_array($driver, ['mysql', 'pgsql']);
        } catch (\Exception $e) {
            return false;
        }
    }
}
