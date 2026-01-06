<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * ProfitMarginAnalysisService - Analyze profit margins across products, categories, and branches
 * 
 * Provides comprehensive profit margin analysis including:
 * - Product-level profitability
 * - Category-level profitability
 * - Branch-level profitability
 * - Time-based trend analysis
 */
class ProfitMarginAnalysisService
{
    /**
     * Get profit margin analysis for products
     */
    public function getProductProfitability(?int $branchId = null, ?Carbon $startDate = null, ?Carbon $endDate = null, int $limit = 20): array
    {
        $startDate = $startDate ?? now()->subDays(30);
        $endDate = $endDate ?? now();

        $query = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->select([
                'products.id',
                'products.name',
                'products.sku',
                DB::raw('COALESCE(SUM(sale_items.quantity), 0) as units_sold'),
                DB::raw('COALESCE(SUM(sale_items.line_total), 0) as revenue'),
                DB::raw('COALESCE(SUM(sale_items.quantity * COALESCE(products.cost, 0)), 0) as cost'),
                DB::raw('COALESCE(SUM(sale_items.line_total), 0) - COALESCE(SUM(sale_items.quantity * COALESCE(products.cost, 0)), 0) as profit'),
                DB::raw('CASE WHEN SUM(sale_items.line_total) > 0 THEN 
                    ((SUM(sale_items.line_total) - SUM(sale_items.quantity * COALESCE(products.cost, 0))) / SUM(sale_items.line_total)) * 100 
                    ELSE 0 END as margin_percent'),
            ])
            ->where('sales.status', '!=', 'cancelled')
            ->whereBetween('sales.created_at', [$startDate, $endDate])
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('profit')
            ->limit($limit);

        if ($branchId) {
            $query->where('sales.branch_id', $branchId);
        }

        $products = $query->get()->toArray();

        // Calculate totals
        $totals = [
            'total_revenue' => array_sum(array_column($products, 'revenue')),
            'total_cost' => array_sum(array_column($products, 'cost')),
            'total_profit' => array_sum(array_column($products, 'profit')),
            'average_margin' => 0,
        ];

        if ($totals['total_revenue'] > 0) {
            $totals['average_margin'] = ($totals['total_profit'] / $totals['total_revenue']) * 100;
        }

        return [
            'products' => $products,
            'totals' => $totals,
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'currency' => setting('general.default_currency', 'EGP'),
        ];
    }

    /**
     * Get profit margin analysis by category
     */
    public function getCategoryProfitability(?int $branchId = null, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->subDays(30);
        $endDate = $endDate ?? now();

        $query = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->leftJoin('product_categories', 'products.category_id', '=', 'product_categories.id')
            ->select([
                'product_categories.id',
                DB::raw('COALESCE(product_categories.name, \'Uncategorized\') as name'),
                DB::raw('COUNT(DISTINCT products.id) as product_count'),
                DB::raw('COALESCE(SUM(sale_items.quantity), 0) as units_sold'),
                DB::raw('COALESCE(SUM(sale_items.line_total), 0) as revenue'),
                DB::raw('COALESCE(SUM(sale_items.quantity * COALESCE(products.cost, 0)), 0) as cost'),
                DB::raw('COALESCE(SUM(sale_items.line_total), 0) - COALESCE(SUM(sale_items.quantity * COALESCE(products.cost, 0)), 0) as profit'),
                DB::raw('CASE WHEN SUM(sale_items.line_total) > 0 THEN 
                    ((SUM(sale_items.line_total) - SUM(sale_items.quantity * COALESCE(products.cost, 0))) / SUM(sale_items.line_total)) * 100 
                    ELSE 0 END as margin_percent'),
            ])
            ->where('sales.status', '!=', 'cancelled')
            ->whereBetween('sales.created_at', [$startDate, $endDate])
            ->groupBy('product_categories.id', 'product_categories.name')
            ->orderByDesc('profit');

        if ($branchId) {
            $query->where('sales.branch_id', $branchId);
        }

        return [
            'categories' => $query->get()->toArray(),
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'currency' => setting('general.default_currency', 'EGP'),
        ];
    }

    /**
     * Get profit trend over time
     */
    public function getProfitTrend(?int $branchId = null, string $groupBy = 'day', int $periods = 30): array
    {
        $dateFormat = match($groupBy) {
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            'year' => '%Y',
            default => '%Y-%m-%d',
        };

        $startDate = match($groupBy) {
            'week' => now()->subWeeks($periods),
            'month' => now()->subMonths($periods),
            'year' => now()->subYears($periods),
            default => now()->subDays($periods),
        };

        $query = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->select([
                DB::raw("DATE_FORMAT(sales.created_at, '{$dateFormat}') as period"),
                DB::raw('COALESCE(SUM(sale_items.line_total), 0) as revenue'),
                DB::raw('COALESCE(SUM(sale_items.quantity * COALESCE(products.cost, 0)), 0) as cost'),
                DB::raw('COALESCE(SUM(sale_items.line_total), 0) - COALESCE(SUM(sale_items.quantity * COALESCE(products.cost, 0)), 0) as profit'),
            ])
            ->where('sales.status', '!=', 'cancelled')
            ->where('sales.created_at', '>=', $startDate)
            ->groupBy(DB::raw("DATE_FORMAT(sales.created_at, '{$dateFormat}')"))
            ->orderBy('period');

        if ($branchId) {
            $query->where('sales.branch_id', $branchId);
        }

        return [
            'trend' => $query->get()->toArray(),
            'group_by' => $groupBy,
            'periods' => $periods,
            'currency' => setting('general.default_currency', 'EGP'),
        ];
    }

    /**
     * Get lowest margin products (items that need attention)
     */
    public function getLowestMarginProducts(?int $branchId = null, int $limit = 10): array
    {
        $query = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->select([
                'products.id',
                'products.name',
                'products.sku',
                'products.cost',
                'products.price',
                DB::raw('COALESCE(SUM(sale_items.quantity), 0) as units_sold'),
                DB::raw('CASE WHEN SUM(sale_items.line_total) > 0 THEN 
                    ((SUM(sale_items.line_total) - SUM(sale_items.quantity * COALESCE(products.cost, 0))) / SUM(sale_items.line_total)) * 100 
                    ELSE 0 END as margin_percent'),
            ])
            ->where('sales.status', '!=', 'cancelled')
            ->where('sales.created_at', '>=', now()->subDays(30))
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.cost', 'products.price')
            ->having('units_sold', '>', 0)
            ->orderBy('margin_percent')
            ->limit($limit);

        if ($branchId) {
            $query->where('sales.branch_id', $branchId);
        }

        return [
            'products' => $query->get()->toArray(),
            'threshold' => 20, // Products below 20% margin need attention
            'currency' => setting('general.default_currency', 'EGP'),
        ];
    }
}
