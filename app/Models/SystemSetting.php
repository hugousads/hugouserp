<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    use HasFactory;

    protected $table = 'system_settings';

    /**
     * Generic key/value settings storage.
     *
     * - key      : unique identifier (e.g. "app.name", "pos.receipt.footer")
     * - value    : json value
     * - type     : optional type hint (string,int,bool,array,json,encrypted,...)
     * - group    : logical group (e.g. "app","mail","pos","hr")
     * - is_public: whether it can be exposed to clients without auth
     */
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'category',
        'is_public',
        'is_encrypted',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'value' => 'array',
        'is_public' => 'bool',
        'is_encrypted' => 'bool',
    ];

    /** Scopes */
    public function scopeKey(Builder $query, string $key): Builder
    {
        return $query->where('key', $key);
    }

    public function scopeGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }

    /**
     * Get a setting value using cache.
     */
    public static function cachedValue(?string $group, string $key, $default = null, int $ttlSeconds = 1800)
    {
        $cacheKey = sprintf('system_setting:%s:%s', $group ?? 'global', $key);

        return Cache::remember($cacheKey, $ttlSeconds, function () use ($group, $key, $default) {
            return static::getValue($group, $key, $default);
        });
    }
}
