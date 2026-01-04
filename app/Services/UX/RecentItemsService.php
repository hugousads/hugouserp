<?php

declare(strict_types=1);

namespace App\Services\UX;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * RecentItemsService - Track and retrieve recently viewed items
 * 
 * Provides functionality to track what items (products, customers, sales, etc.)
 * a user has recently viewed for quick access.
 */
class RecentItemsService
{
    protected const CACHE_TTL = 86400; // 24 hours
    protected const MAX_ITEMS = 20;

    /**
     * Record a view of an item
     */
    public function recordView(int $userId, string $type, int $itemId, ?string $label = null, ?string $route = null): void
    {
        $key = $this->getCacheKey($userId);
        $items = $this->getRecentItems($userId);

        // Remove if already exists (to update position)
        $items = array_filter($items, fn($item) => 
            !($item['type'] === $type && $item['id'] === $itemId)
        );

        // Add to beginning
        array_unshift($items, [
            'type' => $type,
            'id' => $itemId,
            'label' => $label ?? $this->getItemLabel($type, $itemId),
            'route' => $route ?? $this->getItemRoute($type, $itemId),
            'viewed_at' => now()->toISOString(),
        ]);

        // Limit to max items
        $items = array_slice($items, 0, self::MAX_ITEMS);

        Cache::put($key, $items, self::CACHE_TTL);

        // Also persist to database for long-term storage
        $this->persistToDatabase($userId, $type, $itemId, $label, $route);
    }

    /**
     * Get recent items for a user
     */
    public function getRecentItems(int $userId, ?string $type = null, int $limit = 10): array
    {
        $key = $this->getCacheKey($userId);
        $items = Cache::get($key, []);

        if ($type) {
            $items = array_filter($items, fn($item) => $item['type'] === $type);
        }

        return array_slice(array_values($items), 0, $limit);
    }

    /**
     * Get recent items grouped by type
     */
    public function getRecentItemsGrouped(int $userId, int $limitPerType = 5): array
    {
        $items = $this->getRecentItems($userId);
        $grouped = [];

        foreach ($items as $item) {
            $type = $item['type'];
            if (!isset($grouped[$type])) {
                $grouped[$type] = [];
            }
            if (count($grouped[$type]) < $limitPerType) {
                $grouped[$type][] = $item;
            }
        }

        return $grouped;
    }

    /**
     * Clear recent items for a user
     */
    public function clearRecentItems(int $userId, ?string $type = null): void
    {
        if ($type) {
            $key = $this->getCacheKey($userId);
            $items = $this->getRecentItems($userId);
            $items = array_filter($items, fn($item) => $item['type'] !== $type);
            Cache::put($key, array_values($items), self::CACHE_TTL);
        } else {
            Cache::forget($this->getCacheKey($userId));
        }
    }

    /**
     * Get supported item types
     */
    public function getSupportedTypes(): array
    {
        return [
            'product' => [
                'label' => __('Products'),
                'icon' => 'box',
                'color' => 'blue',
            ],
            'customer' => [
                'label' => __('Customers'),
                'icon' => 'users',
                'color' => 'green',
            ],
            'sale' => [
                'label' => __('Sales'),
                'icon' => 'shopping-cart',
                'color' => 'purple',
            ],
            'purchase' => [
                'label' => __('Purchases'),
                'icon' => 'truck',
                'color' => 'orange',
            ],
            'invoice' => [
                'label' => __('Invoices'),
                'icon' => 'file-text',
                'color' => 'red',
            ],
            'supplier' => [
                'label' => __('Suppliers'),
                'icon' => 'building',
                'color' => 'yellow',
            ],
            'report' => [
                'label' => __('Reports'),
                'icon' => 'bar-chart-2',
                'color' => 'cyan',
            ],
        ];
    }

    /**
     * Get cache key for user
     */
    protected function getCacheKey(int $userId): string
    {
        return "recent_items:user:{$userId}";
    }

    /**
     * Get label for an item
     */
    protected function getItemLabel(string $type, int $itemId): string
    {
        return match($type) {
            'product' => DB::table('products')->where('id', $itemId)->value('name') ?? "Product #{$itemId}",
            'customer' => DB::table('customers')->where('id', $itemId)->value('name') ?? "Customer #{$itemId}",
            'sale' => DB::table('sales')->where('id', $itemId)->value('code') ?? "Sale #{$itemId}",
            'purchase' => DB::table('purchases')->where('id', $itemId)->value('code') ?? "Purchase #{$itemId}",
            'invoice' => DB::table('rental_invoices')->where('id', $itemId)->value('code') ?? "Invoice #{$itemId}",
            'supplier' => DB::table('suppliers')->where('id', $itemId)->value('name') ?? "Supplier #{$itemId}",
            'report' => DB::table('report_definitions')->where('id', $itemId)->value('name') ?? "Report #{$itemId}",
            default => "{$type} #{$itemId}",
        };
    }

    /**
     * Get route for an item
     */
    protected function getItemRoute(string $type, int $itemId): string
    {
        return match($type) {
            'product' => route('products.edit', $itemId),
            'customer' => route('customers.edit', $itemId),
            'sale' => route('sales.show', $itemId),
            'purchase' => route('purchases.show', $itemId),
            'invoice' => route('rental.invoices.show', $itemId),
            'supplier' => route('suppliers.edit', $itemId),
            'report' => route('admin.reports.show', $itemId),
            default => '#',
        };
    }

    /**
     * Persist to database for long-term storage
     */
    protected function persistToDatabase(int $userId, string $type, int $itemId, ?string $label, ?string $route): void
    {
        try {
            DB::table('search_history')->updateOrInsert(
                [
                    'user_id' => $userId,
                    'query' => "{$type}:{$itemId}",
                ],
                [
                    'module' => $type,
                    'results_count' => 1,
                    'updated_at' => now(),
                ]
            );
        } catch (\Exception $e) {
            // Silent fail - cache is primary storage
        }
    }
}
