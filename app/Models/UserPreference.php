<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class UserPreference extends Model
{
    protected $fillable = [
        'user_id',
        'theme',
        'session_timeout',
        'auto_logout',
        'default_printer',
        'dashboard_widgets',
        'pos_shortcuts',
        'notification_settings',
    ];

    protected $casts = [
        'auto_logout' => 'boolean',
        'dashboard_widgets' => 'array',
        'pos_shortcuts' => 'array',
        'notification_settings' => 'array',
    ];

    /**
     * Boot the model with cache invalidation on save/delete.
     */
    protected static function booted(): void
    {
        static::saved(function (self $preference) {
            static::clearCacheForUser($preference->user_id);
        });

        static::deleted(function (self $preference) {
            static::clearCacheForUser($preference->user_id);
        });
    }

    /**
     * Clear the cached preferences for a specific user.
     */
    public static function clearCacheForUser(int $userId): void
    {
        Cache::forget(sprintf('user_prefs:%d', $userId));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function getDefaults(): array
    {
        return [
            'theme' => 'light',
            'session_timeout' => 30,
            'auto_logout' => true,
            'default_printer' => null,
            'dashboard_widgets' => [
                'sales_today' => true,
                'revenue_chart' => true,
                'top_products' => true,
                'low_stock' => true,
                'recent_orders' => true,
                'customer_stats' => false,
                'pending_payments' => true,
                'monthly_comparison' => false,
            ],
            'pos_shortcuts' => [
                'F1' => 'new_sale',
                'F2' => 'search_product',
                'F3' => 'search_customer',
                'F4' => 'apply_discount',
                'F5' => 'hold_sale',
                'F6' => 'recall_held',
                'F7' => 'payment_cash',
                'F8' => 'payment_card',
                'F9' => 'print_receipt',
                'F10' => 'void_item',
                'F11' => 'void_sale',
                'F12' => 'close_session',
            ],
            'notification_settings' => [
                'low_stock' => true,
                'new_orders' => true,
                'payment_due' => true,
            ],
        ];
    }

    public static function getForUser(int $userId): self
    {
        $preference = self::where('user_id', $userId)->first();

        if (! $preference) {
            $defaults = self::getDefaults();
            $preference = self::create(array_merge($defaults, ['user_id' => $userId]));
        }

        return $preference;
    }

    public static function cachedForUser(int $userId, int $ttlSeconds = 3600): self
    {
        $cacheKey = sprintf('user_prefs:%d', $userId);

        return Cache::remember($cacheKey, $ttlSeconds, function () use ($userId) {
            return static::getForUser($userId);
        });
    }
}
