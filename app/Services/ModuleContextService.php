<?php

declare(strict_types=1);

namespace App\Services;

/**
 * ModuleContextService
 *
 * Manages UI-level module context for filtering and navigation.
 * Works alongside the existing SetModuleContext middleware for API/route-level module keys.
 *
 * This service provides:
 * - Session-based UI context management
 * - Helper methods for context checking
 * - Compatible with existing module routing system
 */
class ModuleContextService
{
    /**
     * Get the current UI module context.
     */
    public static function current(): string
    {
        return session('module_context', 'all');
    }

    /**
     * Get the route-level module key (set by SetModuleContext middleware).
     * This is used for API routes with {moduleKey} parameter.
     */
    public static function routeKey(): ?string
    {
        return app('req.module_key', null);
    }

    /**
     * Set the UI module context.
     */
    public static function set(string $context): void
    {
        session(['module_context' => $context]);
    }

    /**
     * Check if a specific UI context is active.
     */
    public static function is(string $context): bool
    {
        return self::current() === $context;
    }

    /**
     * Check if "All Modules" context is active.
     */
    public static function isAll(): bool
    {
        return self::current() === 'all';
    }

    /**
     * Get available modules with their labels.
     */
    public static function getAvailableModules(): array
    {
        return [
            'all' => __('All Modules'),
            'inventory' => __('Inventory'),
            'pos' => __('POS'),
            'sales' => __('Sales'),
            'purchases' => __('Purchases'),
            'accounting' => __('Accounting'),
            'warehouse' => __('Warehouse'),
            'manufacturing' => __('Manufacturing'),
            'hrm' => __('Human Resources'),
            'rental' => __('Rental'),
            'fixed_assets' => __('Fixed Assets'),
            'banking' => __('Banking'),
            'projects' => __('Projects'),
            'documents' => __('Documents'),
            'helpdesk' => __('Helpdesk'),
        ];
    }

    /**
     * Get the label for the current context.
     */
    public static function currentLabel(): string
    {
        $context = self::current();
        $modules = self::getAvailableModules();

        return $modules[$context] ?? __('Unknown');
    }

    /**
     * Check if current context matches the route module key.
     * Useful for determining if UI and API contexts are aligned.
     */
    public static function matchesRouteKey(): bool
    {
        $routeKey = self::routeKey();
        if (!$routeKey) {
            return true; // No route key means we're in a non-module route
        }

        $context = self::current();
        if ($context === 'all') {
            return true; // "All" matches everything
        }

        // Map route keys to UI contexts
        $keyMap = [
            'inventory' => 'inventory',
            'pos' => 'pos',
            'sales' => 'sales',
            'purchases' => 'purchases',
            'accounting' => 'accounting',
            'warehouse' => 'warehouse',
            'manufacturing' => 'manufacturing',
            'hrm' => 'hrm',
            'rental' => 'rental',
            'fixed-assets' => 'fixed_assets',
            'banking' => 'banking',
            'projects' => 'projects',
            'documents' => 'documents',
            'helpdesk' => 'helpdesk',
        ];

        $mappedContext = $keyMap[$routeKey] ?? $routeKey;
        return $context === $mappedContext;
    }
}
