<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DashboardWidget;
use App\Models\UserDashboardLayout;
use App\Models\UserDashboardWidget;
use App\Services\Dashboard\DashboardDataService;
use App\Services\Dashboard\DashboardWidgetService;

/**
 * DashboardService - Facade for dashboard services
 * 
 * STATUS: ACTIVE - Production-ready dashboard service
 * PURPOSE: Provide backward-compatible interface to split dashboard services
 * 
 * The functionality has been split into:
 * - DashboardWidgetService: Widget layout and management
 * - DashboardDataService: Widget data generation
 * 
 * This class delegates to the appropriate service while maintaining
 * backward compatibility with existing code.
 */
class DashboardService
{
    protected DashboardWidgetService $widgetService;
    protected DashboardDataService $dataService;

    public function __construct()
    {
        $this->widgetService = new DashboardWidgetService();
        $this->dataService = new DashboardDataService();
    }

    /**
     * Get or create user's default dashboard layout.
     */
    public function getUserDashboard(int $userId, ?int $branchId = null): UserDashboardLayout
    {
        return $this->widgetService->getUserDashboard($userId, $branchId);
    }

    /**
     * Create default dashboard for user.
     */
    public function createDefaultDashboard(int $userId, ?int $branchId = null): UserDashboardLayout
    {
        return $this->widgetService->createDefaultDashboard($userId, $branchId);
    }

    /**
     * Add widget to user's dashboard.
     */
    public function addWidget(int $layoutId, int $widgetId, array $options = []): UserDashboardWidget
    {
        return $this->widgetService->addWidget($layoutId, $widgetId, $options);
    }

    /**
     * Remove widget from dashboard.
     */
    public function removeWidget(int $userWidgetId): void
    {
        $this->widgetService->removeWidget($userWidgetId);
    }

    /**
     * Update widget position/size.
     */
    public function updateWidget(int $userWidgetId, array $data): UserDashboardWidget
    {
        return $this->widgetService->updateWidget($userWidgetId, $data);
    }

    /**
     * Toggle widget visibility.
     */
    public function toggleWidget(int $userWidgetId): bool
    {
        return $this->widgetService->toggleWidget($userWidgetId);
    }

    /**
     * Update dashboard layout.
     */
    public function updateLayout(int $layoutId, array $widgets): void
    {
        $this->widgetService->updateLayout($layoutId, $widgets);
    }

    /**
     * Reset dashboard to default.
     */
    public function resetToDefault(int $layoutId): UserDashboardLayout
    {
        return $this->widgetService->resetToDefault($layoutId);
    }

    /**
     * Get available widgets for user.
     */
    public function getAvailableWidgets($user): array
    {
        return $this->widgetService->getAvailableWidgets($user);
    }

    /**
     * Get widget data with caching.
     */
    public function getWidgetData(int $userId, int $widgetId, ?int $branchId = null, bool $refresh = false): array
    {
        return $this->dataService->getWidgetData($userId, $widgetId, $branchId, $refresh);
    }

    /**
     * Clear widget cache.
     */
    public function clearWidgetCache(int $userId, ?int $widgetId = null): void
    {
        $this->widgetService->clearWidgetCache($userId, $widgetId);
    }

    /**
     * Register a new widget type.
     */
    public function registerWidget(array $data): DashboardWidget
    {
        return $this->widgetService->registerWidget($data);
    }

    /**
     * Get dashboard statistics.
     */
    public function getDashboardStats(int $userId, ?int $branchId = null): array
    {
        return $this->widgetService->getDashboardStats($userId, $branchId);
    }
}
