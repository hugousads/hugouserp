<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\DashboardWidget;
use App\Models\WidgetDataCache;
use Illuminate\Support\Facades\DB;

/**
 * DashboardDataService - Widget data generation
 * 
 * Handles:
 * - Widget data generation for all widget types
 * - Widget data caching
 * - Sales, inventory, HR, helpdesk widget data
 */
class DashboardDataService
{
    /**
     * Get widget data with caching.
     */
    public function getWidgetData(int $userId, int $widgetId, ?int $branchId = null, bool $refresh = false): array
    {
        if (!$refresh) {
            $cached = WidgetDataCache::getCached($userId, $widgetId, $branchId);
            if ($cached !== null) {
                return $cached;
            }
        }

        $widget = DashboardWidget::findOrFail($widgetId);
        $data = $this->generateWidgetData($widget, $userId, $branchId);

        WidgetDataCache::store($userId, $widgetId, $data, $branchId, 30);

        return $data;
    }

    /**
     * Generate widget data based on widget type.
     */
    public function generateWidgetData(DashboardWidget $widget, int $userId, ?int $branchId): array
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
            default => ['message' => 'Widget data generator not implemented for: ' . $widget->key],
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
    public function generateSalesTodayData(?int $branchId): array
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
    public function generateSalesWeekData(?int $branchId): array
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
    public function generateSalesMonthData(?int $branchId): array
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
    public function generateTopSellingProductsData(?int $branchId, int $limit = 5): array
    {
        $query = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->select(
                'products.id',
                'products.name',
                DB::raw('COALESCE(SUM(sale_items.qty), 0) as total_quantity'),
                DB::raw('COALESCE(SUM(sale_items.line_total), 0) as total_revenue')
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
    public function generateTopCustomersData(?int $branchId, int $limit = 5): array
    {
        $query = DB::table('sales')
            ->join('customers', 'sales.customer_id', '=', 'customers.id')
            ->select(
                'customers.id',
                'customers.name',
                DB::raw('COUNT(sales.id) as total_orders'),
                DB::raw('COALESCE(SUM(sales.grand_total), 0) as total_spent')
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
    public function generateLowStockAlertsData(?int $branchId): array
    {
        $query = DB::table('products')
            ->select('id', 'name', 'sku', 'stock_quantity', 'stock_alert_threshold')
            ->whereNotNull('stock_alert_threshold')
            ->whereRaw('COALESCE(stock_quantity, 0) <= stock_alert_threshold')
            ->where('status', 'active')
            ->orderBy('stock_quantity', 'asc')
            ->limit(10);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $products = $query->get()->toArray();

        $countQuery = DB::table('products')
            ->whereNotNull('stock_alert_threshold')
            ->whereRaw('COALESCE(stock_quantity, 0) <= stock_alert_threshold')
            ->where('status', 'active');

        if ($branchId) {
            $countQuery->where('branch_id', $branchId);
        }

        return [
            'products' => $products,
            'total_alerts' => $countQuery->count(),
        ];
    }

    /**
     * Generate rent invoices due data.
     */
    public function generateRentInvoicesDueData(?int $branchId): array
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
    public function generateCashBankBalanceData(?int $branchId): array
    {
        $query = DB::table('bank_accounts')
            ->select([
                'id',
                'account_name as name',
                'account_type',
                'current_balance as balance',
                'currency',
            ])
            ->where('status', 'active');

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
    public function generateTicketsSummaryData(?int $branchId): array
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
    public function generateAttendanceSnapshotData(?int $branchId): array
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
}
