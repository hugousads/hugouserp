<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Get current stock for a product from stock_movements table
     * Compatible with MySQL 8.4, PostgreSQL, and SQLite
     * 
     * Migration schema uses signed `quantity` column:
     * - Positive values = stock in (purchases, returns, adjustments+)
     * - Negative values = stock out (sales, adjustments-)
     */
    public static function getCurrentStock(int $productId, ?int $warehouseId = null): float
    {
        $query = DB::table('stock_movements')
            ->where('product_id', $productId);

        if ($warehouseId !== null) {
            $query->where('warehouse_id', $warehouseId);
        }

        // quantity is signed: positive = in, negative = out
        // Simply sum all quantities to get current stock
        return (float) $query->selectRaw('COALESCE(SUM(quantity), 0) as stock')
            ->value('stock');
    }

    /**
     * Get current stock for multiple products
     * Returns array keyed by product_id
     */
    public static function getBulkCurrentStock(array $productIds, ?int $warehouseId = null): array
    {
        $query = DB::table('stock_movements')
            ->whereIn('product_id', $productIds);

        if ($warehouseId !== null) {
            $query->where('warehouse_id', $warehouseId);
        }

        // quantity is signed: positive = in, negative = out
        $results = $query
            ->select('product_id')
            ->selectRaw('COALESCE(SUM(quantity), 0) as stock')
            ->groupBy('product_id')
            ->get();

        return $results->pluck('stock', 'product_id')->toArray();
    }

    /**
     * Get stock value for a product from stock_movements table
     * Calculates value based on quantity * unit_cost
     */
    public static function getStockValue(int $productId, ?int $warehouseId = null): float
    {
        $query = DB::table('stock_movements')
            ->where('product_id', $productId);

        if ($warehouseId !== null) {
            $query->where('warehouse_id', $warehouseId);
        }

        // Calculate value: SUM(quantity * unit_cost)
        // IFNULL handles NULL unit_cost values
        return (float) ($query->selectRaw('SUM(quantity * IFNULL(unit_cost, 0)) as value')
            ->value('value') ?? 0);
    }

    /**
     * Get SQL expression for calculating current stock
     * Use this for SELECT queries that need to calculate stock on the fly
     * 
     * @param string $productIdColumn Table.column reference (e.g., 'products.id')
     * @throws \InvalidArgumentException if column name contains invalid characters
     */
    public static function getStockCalculationExpression(string $productIdColumn = 'products.id'): string
    {
        // Validate column name to prevent SQL injection
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $productIdColumn)) {
            throw new \InvalidArgumentException('Invalid column name format');
        }

        // quantity is signed: positive = in, negative = out
        return "COALESCE((SELECT SUM(quantity) FROM stock_movements WHERE stock_movements.product_id = {$productIdColumn}), 0)";
    }

    /**
     * Get SQL expression for calculating stock in a specific warehouse
     * 
     * @param string $productIdColumn Table.column reference (e.g., 'products.id')
     * @param string $warehouseIdColumn Table.column reference (e.g., 'warehouses.id')
     * @throws \InvalidArgumentException if column names contain invalid characters
     */
    public static function getWarehouseStockCalculationExpression(string $productIdColumn = 'products.id', string $warehouseIdColumn = 'warehouses.id'): string
    {
        // Validate column names to prevent SQL injection
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $productIdColumn)) {
            throw new \InvalidArgumentException('Invalid product column name format');
        }
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $warehouseIdColumn)) {
            throw new \InvalidArgumentException('Invalid warehouse column name format');
        }

        // quantity is signed: positive = in, negative = out
        return "COALESCE((SELECT SUM(quantity) FROM stock_movements WHERE stock_movements.product_id = {$productIdColumn} AND stock_movements.warehouse_id = {$warehouseIdColumn}), 0)";
    }
}
