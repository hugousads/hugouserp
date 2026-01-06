<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * InventoryTurnoverService - Analyze inventory turnover rates
 * 
 * Provides metrics to understand how quickly inventory moves:
 * - Turnover rate (how many times inventory is sold per period)
 * - Days to sell (average days to sell inventory)
 * - Dead stock identification
 * - Overstocked items
 */
class InventoryTurnoverService
{
    /**
     * Get inventory turnover analysis
     */
    public function getTurnoverAnalysis(?int $branchId = null, int $days = 30): array
    {
        $startDate = now()->subDays($days);

        // Get COGS for the period
        $cogsQuery = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.status', '!=', 'cancelled')
            ->where('sales.created_at', '>=', $startDate);

        if ($branchId) {
            $cogsQuery->where('sales.branch_id', $branchId);
        }

        $cogs = $cogsQuery->sum(DB::raw('sale_items.quantity * COALESCE(products.cost, 0)'));

        // Get average inventory value
        $inventoryQuery = DB::table('products')
            ->where('status', 'active');

        if ($branchId) {
            $inventoryQuery->where('branch_id', $branchId);
        }

        $avgInventoryValue = $inventoryQuery->sum(DB::raw('COALESCE(stock_quantity, 0) * COALESCE(cost, 0)'));

        // Calculate turnover rate
        $turnoverRate = $avgInventoryValue > 0 ? ($cogs / $avgInventoryValue) : 0;
        $daysToSell = $turnoverRate > 0 ? round($days / $turnoverRate) : 0;

        return [
            'cogs' => round($cogs, 2),
            'average_inventory_value' => round($avgInventoryValue, 2),
            'turnover_rate' => round($turnoverRate, 2),
            'days_to_sell' => $daysToSell,
            'period_days' => $days,
            'rating' => $this->getTurnoverRating($turnoverRate, $days),
            'currency' => setting('general.default_currency', 'EGP'),
        ];
    }

    /**
     * Get turnover by product
     */
    public function getProductTurnover(?int $branchId = null, int $days = 30, int $limit = 20): array
    {
        $startDate = now()->subDays($days);

        $query = DB::table('products')
            ->leftJoin(DB::raw('(
                SELECT 
                    sale_items.product_id,
                    SUM(sale_items.quantity) as sold_qty,
                    SUM(sale_items.quantity * COALESCE(products.cost, 0)) as cogs
                FROM sale_items
                JOIN sales ON sale_items.sale_id = sales.id
                JOIN products ON sale_items.product_id = products.id
                WHERE sales.status != \'cancelled\'
                AND sales.created_at >= \'' . $startDate->toDateTimeString() . '\'
                GROUP BY sale_items.product_id
            ) as sales_data'), 'products.id', '=', 'sales_data.product_id')
            ->select([
                'products.id',
                'products.name',
                'products.sku',
                'products.stock_quantity',
                'products.cost',
                DB::raw('COALESCE(products.stock_quantity, 0) * COALESCE(products.cost, 0) as inventory_value'),
                DB::raw('COALESCE(sales_data.sold_qty, 0) as sold_qty'),
                DB::raw('COALESCE(sales_data.cogs, 0) as cogs'),
                DB::raw('CASE 
                    WHEN COALESCE(products.stock_quantity, 0) * COALESCE(products.cost, 0) > 0 
                    THEN COALESCE(sales_data.cogs, 0) / (COALESCE(products.stock_quantity, 0) * COALESCE(products.cost, 0))
                    ELSE 0 
                END as turnover_rate'),
            ])
            ->where('products.status', 'active')
            ->orderByDesc('turnover_rate')
            ->limit($limit);

        if ($branchId) {
            $query->where('products.branch_id', $branchId);
        }

        return [
            'products' => $query->get()->toArray(),
            'period_days' => $days,
            'currency' => setting('general.default_currency', 'EGP'),
        ];
    }

    /**
     * Identify dead stock (items with no sales)
     */
    public function getDeadStock(?int $branchId = null, int $days = 90, int $limit = 20): array
    {
        $startDate = now()->subDays($days);

        $query = DB::table('products')
            ->leftJoin(DB::raw('(
                SELECT DISTINCT sale_items.product_id
                FROM sale_items
                JOIN sales ON sale_items.sale_id = sales.id
                WHERE sales.status != \'cancelled\'
                AND sales.created_at >= \'' . $startDate->toDateTimeString() . '\'
            ) as recent_sales'), 'products.id', '=', 'recent_sales.product_id')
            ->select([
                'products.id',
                'products.name',
                'products.sku',
                'products.stock_quantity',
                'products.cost',
                DB::raw('COALESCE(products.stock_quantity, 0) * COALESCE(products.cost, 0) as tied_up_value'),
                'products.created_at',
            ])
            ->whereNull('recent_sales.product_id')
            ->where('products.status', 'active')
            ->where('products.stock_quantity', '>', 0)
            ->orderByDesc(DB::raw('COALESCE(products.stock_quantity, 0) * COALESCE(products.cost, 0)'))
            ->limit($limit);

        if ($branchId) {
            $query->where('products.branch_id', $branchId);
        }

        $products = $query->get();
        $totalValue = $products->sum('tied_up_value');

        return [
            'products' => $products->toArray(),
            'total_count' => $products->count(),
            'total_tied_up_value' => round($totalValue, 2),
            'period_days' => $days,
            'currency' => setting('general.default_currency', 'EGP'),
        ];
    }

    /**
     * Identify overstocked items
     */
    public function getOverstockedItems(?int $branchId = null, float $threshold = 3.0, int $limit = 20): array
    {
        // Threshold: months of supply (stock_qty / monthly_sales)
        $monthlySalesStart = now()->subDays(30);

        $query = DB::table('products')
            ->leftJoin(DB::raw('(
                SELECT 
                    sale_items.product_id,
                    SUM(sale_items.quantity) as monthly_sales
                FROM sale_items
                JOIN sales ON sale_items.sale_id = sales.id
                WHERE sales.status != \'cancelled\'
                AND sales.created_at >= \'' . $monthlySalesStart->toDateTimeString() . '\'
                GROUP BY sale_items.product_id
            ) as sales_data'), 'products.id', '=', 'sales_data.product_id')
            ->select([
                'products.id',
                'products.name',
                'products.sku',
                'products.stock_quantity',
                'products.cost',
                DB::raw('COALESCE(products.stock_quantity, 0) * COALESCE(products.cost, 0) as inventory_value'),
                DB::raw('COALESCE(sales_data.monthly_sales, 0) as monthly_sales'),
                DB::raw('CASE 
                    WHEN COALESCE(sales_data.monthly_sales, 0) > 0 
                    THEN COALESCE(products.stock_quantity, 0) / sales_data.monthly_sales
                    ELSE 999
                END as months_of_supply'),
            ])
            ->where('products.status', 'active')
            ->where('products.stock_quantity', '>', 0)
            ->having('months_of_supply', '>', $threshold)
            ->orderByDesc('inventory_value')
            ->limit($limit);

        if ($branchId) {
            $query->where('products.branch_id', $branchId);
        }

        return [
            'products' => $query->get()->toArray(),
            'threshold_months' => $threshold,
            'currency' => setting('general.default_currency', 'EGP'),
        ];
    }

    /**
     * Get turnover by category
     */
    public function getCategoryTurnover(?int $branchId = null, int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $query = DB::table('products')
            ->leftJoin('product_categories', 'products.category_id', '=', 'product_categories.id')
            ->leftJoin(DB::raw('(
                SELECT 
                    sale_items.product_id,
                    SUM(sale_items.quantity * COALESCE(p.cost, 0)) as cogs
                FROM sale_items
                JOIN sales ON sale_items.sale_id = sales.id
                JOIN products p ON sale_items.product_id = p.id
                WHERE sales.status != \'cancelled\'
                AND sales.created_at >= \'' . $startDate->toDateTimeString() . '\'
                GROUP BY sale_items.product_id
            ) as sales_data'), 'products.id', '=', 'sales_data.product_id')
            ->select([
                'product_categories.id',
                DB::raw('COALESCE(product_categories.name, \'Uncategorized\') as name'),
                DB::raw('COUNT(products.id) as product_count'),
                DB::raw('SUM(COALESCE(products.stock_quantity, 0) * COALESCE(products.cost, 0)) as inventory_value'),
                DB::raw('SUM(COALESCE(sales_data.cogs, 0)) as cogs'),
                DB::raw('CASE 
                    WHEN SUM(COALESCE(products.stock_quantity, 0) * COALESCE(products.cost, 0)) > 0 
                    THEN SUM(COALESCE(sales_data.cogs, 0)) / SUM(COALESCE(products.stock_quantity, 0) * COALESCE(products.cost, 0))
                    ELSE 0 
                END as turnover_rate'),
            ])
            ->where('products.status', 'active')
            ->groupBy('product_categories.id', 'product_categories.name')
            ->orderByDesc('turnover_rate');

        if ($branchId) {
            $query->where('products.branch_id', $branchId);
        }

        return [
            'categories' => $query->get()->toArray(),
            'period_days' => $days,
            'currency' => setting('general.default_currency', 'EGP'),
        ];
    }

    /**
     * Get turnover rating
     */
    protected function getTurnoverRating(float $rate, int $days): array
    {
        // Annualize the rate
        $annualRate = $rate * (365 / $days);

        if ($annualRate >= 12) {
            return ['score' => 'excellent', 'label' => __('Excellent'), 'color' => 'green'];
        } elseif ($annualRate >= 6) {
            return ['score' => 'good', 'label' => __('Good'), 'color' => 'blue'];
        } elseif ($annualRate >= 4) {
            return ['score' => 'average', 'label' => __('Average'), 'color' => 'yellow'];
        } elseif ($annualRate >= 2) {
            return ['score' => 'low', 'label' => __('Low'), 'color' => 'orange'];
        } else {
            return ['score' => 'poor', 'label' => __('Poor'), 'color' => 'red'];
        }
    }
}
