<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;

class UserFavorite extends Model
{
    protected $fillable = [
        'user_id',
        'favoritable_type',
        'favoritable_id',
        'route_name',
        'label',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function favoritable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to get favorites for current user
     */
    public function scopeForUser(Builder $query, ?int $userId = null): Builder
    {
        $userId = $userId ?? auth()->id();
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to order favorites by sort order
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('created_at', 'desc');
    }

    /**
     * Check if user has favorited a specific item
     */
    public static function isFavorited(?string $type = null, ?int $id = null, ?string $route = null): bool
    {
        $query = static::forUser();

        if ($type && $id) {
            $query->where('favoritable_type', $type)->where('favoritable_id', $id);
        } elseif ($route) {
            $query->where('route_name', $route);
        }

        return $query->exists();
    }

    /**
     * Toggle favorite
     */
    public static function toggle(?string $type = null, ?int $id = null, ?string $route = null, ?string $label = null): bool
    {
        $query = static::forUser();

        if ($type && $id) {
            $query->where('favoritable_type', $type)->where('favoritable_id', $id);
        } elseif ($route) {
            $query->where('route_name', $route);
        }

        $favorite = $query->first();

        if ($favorite) {
            $favorite->delete();
            return false;
        }

        static::create([
            'user_id' => auth()->id(),
            'favoritable_type' => $type,
            'favoritable_id' => $id,
            'route_name' => $route,
            'label' => $label ?? $route ?? 'Favorite',
        ]);

        return true;
    }
}
