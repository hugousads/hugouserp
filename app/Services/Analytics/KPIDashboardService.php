<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Expense;
use App\Traits\HandlesServiceErrors;
use Illuminate\Support\Facades\DB;

/**
 * KPI Dashboard Service
 * 
 * Provides key performance indicators for the ERP system
 * including sales, inventory, customer, and financial metrics.
 */
class KPIDashboardService
{
    use HandlesServiceErrors;

    /**
     * Get all KPIs for dashboard
     */
    public function getAllKPIs(?int $branchId = null, ?string $period = 'month'): array
    {
        return $this->handleServiceOperation(
            callback: function () use ($branchId, $period) {
                $dates = $this->getPeriodDates($period);
                $previousDates = $this->getPreviousPeriodDates($period);

                return [
                    'sales' => $this->getSalesKPIs($branchId, $dates, $previousDates),
                    'inventory' => $this->getInventoryKPIs($branchId),
                    'customers' => $this->getCustomerKPIs($branchId, $dates, $previousDates),
                    'financial' => $this->getFinancialKPIs($branchId, $dates, $previousDates),
                    'performance' => $this->getPerformanceKPIs($branchId, $dates),
                    'period' => [
                        'current' => $dates,
                        'previous' => $previousDates,
                        'label' => $this->getPeriodLabel($period),
                    ],
                ];
            },
            operation: 'getAllKPIs',
            context: ['branch_id' => $branchId, 'period' => $period]
        );
    }

    /**
     * Get sales KPIs
     */
    public function getSalesKPIs(?int $branchId, array $dates, array $previousDates): array
    {
        // Current period sales
        $currentSales = $this->getSalesData($branchId, $dates['start'], $dates['end']);
        $previousSales = $this->getSalesData($branchId, $previousDates['start'], $previousDates['end']);

        // Calculate changes
        $revenueChange = $this->calculateChange($currentSales['total_revenue'], $previousSales['total_revenue']);
        $ordersChange = $this->calculateChange($currentSales['total_orders'], $previousSales['total_orders']);
        $aovChange = $this->calculateChange($currentSales['avg_order_value'], $previousSales['avg_order_value']);

        return [
            'total_revenue' => [
                'value' => round($currentSales['total_revenue'], 2),
                'change' => $revenueChange,
                'previous' => round($previousSales['total_revenue'], 2),
                'trend' => $revenueChange >= 0 ? 'up' : 'down',
            ],
            'total_orders' => [
                'value' => $currentSales['total_orders'],
                'change' => $ordersChange,
                'previous' => $previousSales['total_orders'],
                'trend' => $ordersChange >= 0 ? 'up' : 'down',
            ],
            'avg_order_value' => [
                'value' => round($currentSales['avg_order_value'], 2),
                'change' => $aovChange,
                'previous' => round($previousSales['avg_order_value'], 2),
                'trend' => $aovChange >= 0 ? 'up' : 'down',
            ],
            'conversion_rate' => [
                'value' => $this->getConversionRate($branchId, $dates),
                'target' => 15, // Target conversion rate %
            ],
        ];
    }

    /**
     * Get inventory KPIs
     */
    public function getInventoryKPIs(?int $branchId): array
    {
        $query = Product::query()
            ->where('is_active', true);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $totalProducts = $query->count();
        
        // Low stock products
        $lowStockCount = Product::query()
            ->where('is_active', true)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereColumn('qty', '<=', 'reorder_level')
            ->where('reorder_level', '>', 0)
            ->count();

        // Out of stock products
        $outOfStockCount = Product::query()
            ->where('is_active', true)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('qty', '<=', 0)
            ->count();

        // Total inventory value
        $inventoryValue = Product::query()
            ->where('is_active', true)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->sum(DB::raw('qty * COALESCE(cost, 0)'));

        return [
            'total_products' => [
                'value' => $totalProducts,
                'label' => __('Total Products'),
            ],
            'low_stock' => [
                'value' => $lowStockCount,
                'percentage' => $totalProducts > 0 ? round(($lowStockCount / $totalProducts) * 100, 1) : 0,
                'label' => __('Low Stock'),
                'alert' => $lowStockCount > 10,
            ],
            'out_of_stock' => [
                'value' => $outOfStockCount,
                'percentage' => $totalProducts > 0 ? round(($outOfStockCount / $totalProducts) * 100, 1) : 0,
                'label' => __('Out of Stock'),
                'alert' => $outOfStockCount > 0,
            ],
            'inventory_value' => [
                'value' => round($inventoryValue, 2),
                'label' => __('Inventory Value'),
            ],
        ];
    }

    /**
     * Get customer KPIs
     */
    public function getCustomerKPIs(?int $branchId, array $dates, array $previousDates): array
    {
        // New customers this period
        $newCustomers = Customer::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('created_at', [$dates['start'], $dates['end']])
            ->count();

        $previousNewCustomers = Customer::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('created_at', [$previousDates['start'], $previousDates['end']])
            ->count();

        // Total active customers (purchased in period)
        $activeCustomers = Sale::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('created_at', [$dates['start'], $dates['end']])
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('customer_id')
            ->distinct('customer_id')
            ->count('customer_id');

        // Repeat customers
        $repeatCustomers = DB::table('sales')
            ->select('customer_id', DB::raw('COUNT(*) as order_count'))
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('created_at', [$dates['start'], $dates['end']])
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->having('order_count', '>', 1)
            ->count();

        $newCustomersChange = $this->calculateChange($newCustomers, $previousNewCustomers);

        return [
            'new_customers' => [
                'value' => $newCustomers,
                'change' => $newCustomersChange,
                'previous' => $previousNewCustomers,
                'trend' => $newCustomersChange >= 0 ? 'up' : 'down',
            ],
            'active_customers' => [
                'value' => $activeCustomers,
                'label' => __('Active Customers'),
            ],
            'repeat_rate' => [
                'value' => $activeCustomers > 0 ? round(($repeatCustomers / $activeCustomers) * 100, 1) : 0,
                'label' => __('Repeat Rate'),
                'target' => 30, // Target repeat rate %
            ],
            'customer_acquisition_cost' => [
                'value' => $newCustomers > 0 ? round($this->getMarketingExpenses($branchId, $dates) / $newCustomers, 2) : 0,
                'label' => __('Acquisition Cost'),
            ],
        ];
    }

    /**
     * Get financial KPIs
     */
    public function getFinancialKPIs(?int $branchId, array $dates, array $previousDates): array
    {
        // Revenue and expenses
        $currentRevenue = Sale::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('created_at', [$dates['start'], $dates['end']])
            ->where('status', '!=', 'cancelled')
            ->sum('grand_total');

        $currentExpenses = Expense::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('expense_date', [$dates['start'], $dates['end']])
            ->where('status', 'approved')
            ->sum('amount');

        $previousRevenue = Sale::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('created_at', [$previousDates['start'], $previousDates['end']])
            ->where('status', '!=', 'cancelled')
            ->sum('grand_total');

        $previousExpenses = Expense::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('expense_date', [$previousDates['start'], $previousDates['end']])
            ->where('status', 'approved')
            ->sum('amount');

        // Purchases (COGS approximation)
        $currentPurchases = Purchase::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('created_at', [$dates['start'], $dates['end']])
            ->where('status', '!=', 'cancelled')
            ->sum('grand_total');

        // Calculate gross profit
        $grossProfit = $currentRevenue - $currentPurchases;
        $netProfit = $grossProfit - $currentExpenses;
        
        $previousGrossProfit = $previousRevenue - Purchase::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('created_at', [$previousDates['start'], $previousDates['end']])
            ->where('status', '!=', 'cancelled')
            ->sum('grand_total');

        $grossProfitChange = $this->calculateChange($grossProfit, $previousGrossProfit);

        return [
            'gross_profit' => [
                'value' => round($grossProfit, 2),
                'change' => $grossProfitChange,
                'margin' => $currentRevenue > 0 ? round(($grossProfit / $currentRevenue) * 100, 1) : 0,
                'trend' => $grossProfitChange >= 0 ? 'up' : 'down',
            ],
            'net_profit' => [
                'value' => round($netProfit, 2),
                'margin' => $currentRevenue > 0 ? round(($netProfit / $currentRevenue) * 100, 1) : 0,
            ],
            'total_expenses' => [
                'value' => round($currentExpenses, 2),
                'change' => $this->calculateChange($currentExpenses, $previousExpenses),
            ],
            'expense_ratio' => [
                'value' => $currentRevenue > 0 ? round(($currentExpenses / $currentRevenue) * 100, 1) : 0,
                'target' => 30, // Target expense ratio %
            ],
        ];
    }

    /**
     * Get performance KPIs
     */
    public function getPerformanceKPIs(?int $branchId, array $dates): array
    {
        // Average time to fulfill order (if tracking exists)
        $avgFulfillmentTime = 0; // Placeholder - implement based on your order tracking

        // Top selling products
        $topProducts = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->select(
                'products.name',
                DB::raw('SUM(sale_items.quantity) as total_qty'),
                DB::raw('SUM(sale_items.line_total) as total_revenue')
            )
            ->when($branchId, fn($q) => $q->where('sales.branch_id', $branchId))
            ->whereBetween('sales.created_at', [$dates['start'], $dates['end']])
            ->where('sales.status', '!=', 'cancelled')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get();

        // Daily sales trend
        $dailySales = Sale::query()
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(grand_total) as revenue'),
                DB::raw('COUNT(*) as orders')
            )
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('created_at', [$dates['start'], $dates['end']])
            ->where('status', '!=', 'cancelled')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'top_products' => $topProducts->map(fn($p) => [
                'name' => $p->name,
                'quantity' => $p->total_qty,
                'revenue' => round($p->total_revenue, 2),
            ])->toArray(),
            'daily_sales' => $dailySales->map(fn($d) => [
                'date' => $d->date,
                'revenue' => round($d->revenue, 2),
                'orders' => $d->orders,
            ])->toArray(),
            'avg_fulfillment_time' => $avgFulfillmentTime,
        ];
    }

    /**
     * Get sales data for a period
     */
    protected function getSalesData(?int $branchId, string $start, string $end): array
    {
        $query = Sale::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('created_at', [$start, $end])
            ->where('status', '!=', 'cancelled');

        $totalRevenue = $query->sum('grand_total');
        $totalOrders = $query->count();
        $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        return [
            'total_revenue' => $totalRevenue,
            'total_orders' => $totalOrders,
            'avg_order_value' => $avgOrderValue,
        ];
    }

    /**
     * Calculate percentage change
     */
    protected function calculateChange(float $current, float $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Get period dates
     */
    protected function getPeriodDates(string $period): array
    {
        return match($period) {
            'week' => [
                'start' => now()->startOfWeek()->toDateString(),
                'end' => now()->endOfWeek()->toDateString(),
            ],
            'month' => [
                'start' => now()->startOfMonth()->toDateString(),
                'end' => now()->endOfMonth()->toDateString(),
            ],
            'quarter' => [
                'start' => now()->firstOfQuarter()->toDateString(),
                'end' => now()->lastOfQuarter()->toDateString(),
            ],
            'year' => [
                'start' => now()->startOfYear()->toDateString(),
                'end' => now()->endOfYear()->toDateString(),
            ],
            default => [
                'start' => now()->startOfMonth()->toDateString(),
                'end' => now()->endOfMonth()->toDateString(),
            ],
        };
    }

    /**
     * Get previous period dates
     */
    protected function getPreviousPeriodDates(string $period): array
    {
        return match($period) {
            'week' => [
                'start' => now()->subWeek()->startOfWeek()->toDateString(),
                'end' => now()->subWeek()->endOfWeek()->toDateString(),
            ],
            'month' => [
                'start' => now()->subMonth()->startOfMonth()->toDateString(),
                'end' => now()->subMonth()->endOfMonth()->toDateString(),
            ],
            'quarter' => [
                'start' => now()->subQuarter()->firstOfQuarter()->toDateString(),
                'end' => now()->subQuarter()->lastOfQuarter()->toDateString(),
            ],
            'year' => [
                'start' => now()->subYear()->startOfYear()->toDateString(),
                'end' => now()->subYear()->endOfYear()->toDateString(),
            ],
            default => [
                'start' => now()->subMonth()->startOfMonth()->toDateString(),
                'end' => now()->subMonth()->endOfMonth()->toDateString(),
            ],
        };
    }

    /**
     * Get period label
     */
    protected function getPeriodLabel(string $period): string
    {
        return match($period) {
            'week' => __('This Week'),
            'month' => __('This Month'),
            'quarter' => __('This Quarter'),
            'year' => __('This Year'),
            default => __('This Month'),
        };
    }

    /**
     * Get conversion rate
     */
    protected function getConversionRate(?int $branchId, array $dates): float
    {
        // This would typically compare visitors to customers
        // Simplified: compare quotes/inquiries to sales
        return 0; // Implement based on your tracking
    }

    /**
     * Get marketing expenses
     */
    protected function getMarketingExpenses(?int $branchId, array $dates): float
    {
        return Expense::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('expense_date', [$dates['start'], $dates['end']])
            ->where('status', 'approved')
            ->whereHas('category', fn($q) => $q->where('name', 'like', '%marketing%'))
            ->sum('amount');
    }
}
