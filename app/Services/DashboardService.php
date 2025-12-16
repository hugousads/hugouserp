<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DashboardWidget;
use App\Models\UserDashboardLayout;
use App\Models\UserDashboardWidget;
use App\Models\WidgetDataCache;
use Illuminate\Support\Facades\DB;

/**
 * DashboardService - Customizable dashboard and widget management
 * 
 * STATUS: ACTIVE - Production-ready dashboard service
 * PURPOSE: Manage user dashboards, widgets, layouts, and widget data generation
 * FEATURES: 
 *   - User-customizable dashboard layouts
 *   - Widget drag-and-drop positioning
 *   - Real-time widget data generation (sales, inventory, tickets, attendance)
 *   - Widget caching for performance
 * USAGE: Called by dashboard controllers/components for dynamic dashboards
 * 
 * This service is fully implemented and provides comprehensive dashboard
 * functionality with 10+ widget types for sales, inventory, HR, and helpdesk modules.
 */
class DashboardService
{
    /**
     * Get or create user's default dashboard layout.
     */
    public function getUserDashboard(int $userId, ?int $branchId = null): UserDashboardLayout
    {
        $layout = UserDashboardLayout::where('user_id', $userId)
            ->where('branch_id', $branchId)
            ->where('is_default', true)
            ->first();

        if (! $layout) {
            $layout = $this->createDefaultDashboard($userId, $branchId);
        }

        return $layout->load(['widgets.widget']);
    }

    /**
     * Create default dashboard for user.
     */
    public function createDefaultDashboard(int $userId, ?int $branchId = null): UserDashboardLayout
    {
        return DB::transaction(function () use ($userId, $branchId) {
            $layout = UserDashboardLayout::create([
                'user_id' => $userId,
                'branch_id' => $branchId,
                'name' => __('My Dashboard'),
                'is_default' => true,
                'layout_config' => [
                    'columns' => 12,
                    'row_height' => 100,
                    'gap' => 16,
                ],
            ]);

            // Add default widgets based on user permissions
            $this->addDefaultWidgets($layout);

            return $layout;
        });
    }

    /**
     * Add default widgets to layout.
     */
    private function addDefaultWidgets(UserDashboardLayout $layout): void
    {
        $user = $layout->user;
        $widgets = DashboardWidget::active()->ordered()->get();

        $positionY = 0;
        $positionX = 0;

        foreach ($widgets as $widget) {
            // Check permissions
            if (! $widget->userCanView($user)) {
                continue;
            }

            // Calculate position
            if ($positionX + $widget->default_width > 12) {
                $positionX = 0;
                $positionY += 4; // Move to next row
            }

            UserDashboardWidget::create([
                'user_dashboard_layout_id' => $layout->id,
                'dashboard_widget_id' => $widget->id,
                'position_x' => $positionX,
                'position_y' => $positionY,
                'width' => $widget->default_width,
                'height' => $widget->default_height,
                'settings' => $widget->default_settings,
                'is_visible' => true,
                'sort_order' => $widget->sort_order,
            ]);

            $positionX += $widget->default_width;
        }
    }

    /**
     * Add widget to user's dashboard.
     */
    public function addWidget(int $layoutId, int $widgetId, array $options = []): UserDashboardWidget
    {
        $layout = UserDashboardLayout::findOrFail($layoutId);
        $widget = DashboardWidget::findOrFail($widgetId);

        // Check if already exists
        $existing = UserDashboardWidget::where('user_dashboard_layout_id', $layoutId)
            ->where('dashboard_widget_id', $widgetId)
            ->first();

        if ($existing) {
            return $existing;
        }

        return UserDashboardWidget::create([
            'user_dashboard_layout_id' => $layoutId,
            'dashboard_widget_id' => $widgetId,
            'position_x' => $options['position_x'] ?? 0,
            'position_y' => $options['position_y'] ?? 0,
            'width' => $options['width'] ?? $widget->default_width,
            'height' => $options['height'] ?? $widget->default_height,
            'settings' => $options['settings'] ?? $widget->default_settings,
            'is_visible' => $options['is_visible'] ?? true,
            'sort_order' => $options['sort_order'] ?? 999,
        ]);
    }

    /**
     * Remove widget from dashboard.
     */
    public function removeWidget(int $userWidgetId): void
    {
        UserDashboardWidget::findOrFail($userWidgetId)->delete();
    }

    /**
     * Update widget position/size.
     */
    public function updateWidget(int $userWidgetId, array $data): UserDashboardWidget
    {
        $userWidget = UserDashboardWidget::findOrFail($userWidgetId);

        $userWidget->update(array_filter([
            'position_x' => $data['position_x'] ?? null,
            'position_y' => $data['position_y'] ?? null,
            'width' => $data['width'] ?? null,
            'height' => $data['height'] ?? null,
            'settings' => $data['settings'] ?? null,
            'is_visible' => $data['is_visible'] ?? null,
        ], fn ($value) => ! is_null($value)));

        return $userWidget;
    }

    /**
     * Toggle widget visibility.
     */
    public function toggleWidget(int $userWidgetId): bool
    {
        $userWidget = UserDashboardWidget::findOrFail($userWidgetId);
        $userWidget->update(['is_visible' => ! $userWidget->is_visible]);

        return $userWidget->is_visible;
    }

    /**
     * Update dashboard layout.
     */
    public function updateLayout(int $layoutId, array $widgets): void
    {
        DB::transaction(function () use ($layoutId, $widgets) {
            foreach ($widgets as $widgetData) {
                UserDashboardWidget::where('id', $widgetData['id'])
                    ->where('user_dashboard_layout_id', $layoutId)
                    ->update([
                        'position_x' => $widgetData['position_x'],
                        'position_y' => $widgetData['position_y'],
                        'width' => $widgetData['width'],
                        'height' => $widgetData['height'],
                    ]);
            }
        });
    }

    /**
     * Reset dashboard to default.
     */
    public function resetToDefault(int $layoutId): UserDashboardLayout
    {
        return DB::transaction(function () use ($layoutId) {
            $layout = UserDashboardLayout::findOrFail($layoutId);

            // Delete all widgets
            $layout->widgets()->delete();

            // Re-add default widgets
            $this->addDefaultWidgets($layout);

            return $layout->fresh(['widgets.widget']);
        });
    }

    /**
     * Get available widgets for user.
     */
    public function getAvailableWidgets($user): array
    {
        return DashboardWidget::active()
            ->ordered()
            ->get()
            ->filter(fn ($widget) => $widget->userCanView($user))
            ->map(fn ($widget) => [
                'id' => $widget->id,
                'key' => $widget->key,
                'name' => $widget->localized_name,
                'description' => $widget->description,
                'icon' => $widget->icon,
                'category' => $widget->category,
                'default_width' => $widget->default_width,
                'default_height' => $widget->default_height,
                'configurable_options' => $widget->configurable_options,
            ])
            ->groupBy('category')
            ->toArray();
    }

    /**
     * Get widget data with caching.
     */
    public function getWidgetData(int $userId, int $widgetId, ?int $branchId = null, bool $refresh = false): array
    {
        // Check cache first
        if (! $refresh) {
            $cached = WidgetDataCache::getCached($userId, $widgetId, $branchId);
            if ($cached !== null) {
                return $cached;
            }
        }

        // Generate fresh data
        $widget = DashboardWidget::findOrFail($widgetId);
        $data = $this->generateWidgetData($widget, $userId, $branchId);

        // Cache it
        WidgetDataCache::store($userId, $widgetId, $data, $branchId, 30);

        return $data;
    }

    /**
     * Generate widget data based on widget type.
     */
    private function generateWidgetData(DashboardWidget $widget, int $userId, ?int $branchId): array
    {
        $data = match ($widget->key) {
            'sales_today' => $this->generateSalesTodayData($branchId),
            'sales_this_week' => $this->generateSalesWeekData($branchId),
            'sales_this_month' => $this->generateSalesMonthData($branchId),
            'top_selling_products' => $this->generateTopSellingProductsData($branchId),
            'top_customers' => $this->generateTopCustomersData($branchId),
            'low_stock_alerts' => $this->generateLowStockAlertsData($branchId),
            'rent_invoices_due' => $this->generateRentInvoicesDueData($branchId),
            'cash_bank_balance' => $this->generateCashBankBalanceData($branchId),
            'tickets_summary' => $this->generateTicketsSummaryData($branchId),
            'attendance_snapshot' => $this->generateAttendanceSnapshotData($branchId),
            default => ['message' => 'Widget data generator not implemented for: '.$widget->key],
        };

        return [
            'widget_id' => $widget->id,
            'widget_key' => $widget->key,
            'data' => $data,
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Generate sales today data.
     */
    private function generateSalesTodayData(?int $branchId): array
    {
        $query = DB::table('sales')
            ->whereDate('created_at', today())
            ->where('status', '!=', 'cancelled');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $totalSales = $query->sum('grand_total') ?? 0;
        $totalOrders = $query->count();
        $averageOrder = $totalOrders > 0 ? $totalSales / $totalOrders : 0;

        return [
            'total_sales' => $totalSales,
            'total_orders' => $totalOrders,
            'average_order' => $averageOrder,
            'currency' => setting('general.default_currency', 'EGP'),
        ];
    }

    /**
     * Generate sales this week data.
     */
    private function generateSalesWeekData(?int $branchId): array
    {
        $query = DB::table('sales')
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->where('status', '!=', 'cancelled');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return [
            'total_sales' => $query->sum('grand_total') ?? 0,
            'total_orders' => $query->count(),
            'currency' => setting('general.default_currency', 'EGP'),
        ];
    }

    /**
     * Generate sales this month data.
     */
    private function generateSalesMonthData(?int $branchId): array
    {
        $query = DB::table('sales')
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->where('status', '!=', 'cancelled');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return [
            'total_sales' => $query->sum('grand_total') ?? 0,
            'total_orders' => $query->count(),
            'currency' => setting('general.default_currency', 'EGP'),
        ];
    }

    /**
     * Generate top selling products data.
     */
    private function generateTopSellingProductsData(?int $branchId, int $limit = 5): array
    {
        $query = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->select(
                'products.id',
                'products.name',
                DB::raw('SUM(sale_items.qty) as total_quantity'),
                DB::raw('SUM(sale_items.line_total) as total_revenue')
            )
            ->where('sales.status', '!=', 'cancelled')
            ->whereBetween('sales.created_at', [now()->subDays(30), now()])
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_quantity')
            ->limit($limit);

        if ($branchId) {
            $query->where('sales.branch_id', $branchId);
        }

        return [
            'products' => $query->get()->toArray(),
            'currency' => setting('general.default_currency', 'EGP'),
        ];
    }

    /**
     * Generate top customers data.
     */
    private function generateTopCustomersData(?int $branchId, int $limit = 5): array
    {
        $query = DB::table('sales')
            ->join('customers', 'sales.customer_id', '=', 'customers.id')
            ->select(
                'customers.id',
                'customers.name',
                DB::raw('COUNT(sales.id) as total_orders'),
                DB::raw('SUM(sales.grand_total) as total_spent')
            )
            ->where('sales.status', '!=', 'cancelled')
            ->whereBetween('sales.created_at', [now()->subDays(30), now()])
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('total_spent')
            ->limit($limit);

        if ($branchId) {
            $query->where('sales.branch_id', $branchId);
        }

        return [
            'customers' => $query->get()->toArray(),
            'currency' => setting('general.default_currency', 'EGP'),
        ];
    }

    /**
     * Generate low stock alerts data.
     */
    private function generateLowStockAlertsData(?int $branchId): array
    {
        $query = DB::table('products')
            ->select('id', 'name', 'sku', 'stock_quantity', 'stock_alert_threshold')
            ->whereNotNull('stock_alert_threshold')
            ->whereRaw('stock_quantity <= stock_alert_threshold')
            ->orderBy('stock_quantity', 'asc')
            ->limit(10);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return [
            'products' => $query->get()->toArray(),
            'total_alerts' => $query->count(),
        ];
    }

    /**
     * Generate rent invoices due data.
     */
    private function generateRentInvoicesDueData(?int $branchId): array
    {
        $query = DB::table('rental_invoices')
            ->select('id', 'code', 'contract_id', 'amount', 'due_date', 'status')
            ->where('status', 'pending')
            ->whereBetween('due_date', [now(), now()->addDays(7)])
            ->orderBy('due_date', 'asc')
            ->limit(10);

        $invoices = $query->get()->toArray();
        $totalAmount = collect($invoices)->sum('amount');

        return [
            'invoices' => $invoices,
            'total_amount' => $totalAmount,
            'count' => count($invoices),
            'currency' => setting('general.default_currency', 'EGP'),
        ];
    }

    /**
     * Generate cash and bank balance data.
     */
    private function generateCashBankBalanceData(?int $branchId): array
    {
        $query = DB::table('bank_accounts')
            ->select('id', 'name', 'account_type', 'balance', 'currency')
            ->where('is_active', true);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $accounts = $query->get()->toArray();
        $totalBalance = collect($accounts)->sum('balance');

        return [
            'accounts' => $accounts,
            'total_balance' => $totalBalance,
            'currency' => setting('general.default_currency', 'EGP'),
        ];
    }

    /**
     * Generate tickets summary data.
     */
    private function generateTicketsSummaryData(?int $branchId): array
    {
        $query = DB::table('tickets');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return [
            'open' => (clone $query)->where('status', 'open')->count(),
            'in_progress' => (clone $query)->where('status', 'in_progress')->count(),
            'on_hold' => (clone $query)->where('status', 'on_hold')->count(),
            'resolved' => (clone $query)->where('status', 'resolved')->count(),
            'overdue' => (clone $query)->where('status', 'open')->where('due_date', '<', now())->count(),
        ];
    }

    /**
     * Generate attendance snapshot data.
     */
    private function generateAttendanceSnapshotData(?int $branchId): array
    {
        $query = DB::table('attendances')
            ->whereDate('date', today());

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $total = DB::table('hr_employees')->where('is_active', true);
        if ($branchId) {
            $total->where('branch_id', $branchId);
        }
        $totalEmployees = $total->count();

        return [
            'present' => (clone $query)->where('status', 'present')->count(),
            'absent' => (clone $query)->where('status', 'absent')->count(),
            'late' => (clone $query)->where('is_late', true)->count(),
            'on_leave' => (clone $query)->where('status', 'leave')->count(),
            'total_employees' => $totalEmployees,
        ];
    }

    /**
     * Clear widget cache.
     */
    public function clearWidgetCache(int $userId, ?int $widgetId = null): void
    {
        $query = WidgetDataCache::where('user_id', $userId);

        if ($widgetId) {
            $query->where('dashboard_widget_id', $widgetId);
        }

        $query->delete();
    }

    /**
     * Register a new widget type.
     */
    public function registerWidget(array $data): DashboardWidget
    {
        return DashboardWidget::create($data);
    }

    /**
     * Get dashboard statistics.
     */
    public function getDashboardStats(int $userId, ?int $branchId = null): array
    {
        $layout = $this->getUserDashboard($userId, $branchId);

        return [
            'total_widgets' => $layout->widgets()->count(),
            'visible_widgets' => $layout->widgets()->where('is_visible', true)->count(),
            'available_widgets' => DashboardWidget::active()->count(),
            'layout' => [
                'columns' => $layout->layout_config['columns'] ?? 12,
                'row_height' => $layout->layout_config['row_height'] ?? 100,
            ],
        ];
    }
}
