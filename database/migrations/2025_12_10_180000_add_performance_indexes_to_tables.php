<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add performance indexes for commonly filtered columns
 * These indexes improve query performance across the ERP system
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Products - commonly filtered by status, type, and branch
        Schema::table('products', function (Blueprint $table) {
            if (! $this->indexExists('products', 'products_status_type_idx')) {
                $table->index(['status', 'type'], 'products_status_type_idx');
            }
            if (! $this->indexExists('products', 'products_branch_status_idx')) {
                $table->index(['branch_id', 'status'], 'products_branch_status_idx');
            }
        });

        // Stock Movements - heavily queried for stock calculations
        Schema::table('stock_movements', function (Blueprint $table) {
            if (! $this->indexExists('stock_movements', 'stock_movements_product_direction_idx')) {
                $table->index(['product_id', 'direction'], 'stock_movements_product_direction_idx');
            }
            if (! $this->indexExists('stock_movements', 'stock_movements_warehouse_date_idx')) {
                $table->index(['warehouse_id', 'created_at'], 'stock_movements_warehouse_date_idx');
            }
        });

        // Sales - common filters for reports and analytics
        Schema::table('sales', function (Blueprint $table) {
            if (! $this->indexExists('sales', 'sales_status_date_idx')) {
                $table->index(['status', 'created_at'], 'sales_status_date_idx');
            }
            if (! $this->indexExists('sales', 'sales_branch_customer_idx')) {
                $table->index(['branch_id', 'customer_id'], 'sales_branch_customer_idx');
            }
        });

        // Sale Items - for product performance reports
        Schema::table('sale_items', function (Blueprint $table) {
            if (! $this->indexExists('sale_items', 'sale_items_product_idx')) {
                $table->index('product_id', 'sale_items_product_idx');
            }
        });

        // Sale Payments - for payment method analytics
        Schema::table('sale_payments', function (Blueprint $table) {
            if (! $this->indexExists('sale_payments', 'sale_payments_method_idx')) {
                $table->index('payment_method', 'sale_payments_method_idx');
            }
            if (! $this->indexExists('sale_payments', 'sale_payments_created_idx')) {
                $table->index('created_at', 'sale_payments_created_idx');
            }
        });

        // Purchases - similar to sales
        Schema::table('purchases', function (Blueprint $table) {
            if (! $this->indexExists('purchases', 'purchases_status_date_idx')) {
                $table->index(['status', 'created_at'], 'purchases_status_date_idx');
            }
            if (! $this->indexExists('purchases', 'purchases_branch_supplier_idx')) {
                $table->index(['branch_id', 'supplier_id'], 'purchases_branch_supplier_idx');
            }
        });

        // Customers - commonly searched
        Schema::table('customers', function (Blueprint $table) {
            if (! $this->indexExists('customers', 'customers_active_branch_idx')) {
                $table->index(['is_active', 'branch_id'], 'customers_active_branch_idx');
            }
        });

        // Suppliers - commonly searched
        Schema::table('suppliers', function (Blueprint $table) {
            if (! $this->indexExists('suppliers', 'suppliers_active_branch_idx')) {
                $table->index(['is_active', 'branch_id'], 'suppliers_active_branch_idx');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $this->safeDropIndex($table, 'products_status_type_idx');
            $this->safeDropIndex($table, 'products_branch_status_idx');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $this->safeDropIndex($table, 'stock_movements_product_direction_idx');
            $this->safeDropIndex($table, 'stock_movements_warehouse_date_idx');
        });

        Schema::table('sales', function (Blueprint $table) {
            $this->safeDropIndex($table, 'sales_status_date_idx');
            $this->safeDropIndex($table, 'sales_branch_customer_idx');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $this->safeDropIndex($table, 'sale_items_product_idx');
        });

        Schema::table('sale_payments', function (Blueprint $table) {
            $this->safeDropIndex($table, 'sale_payments_method_idx');
            $this->safeDropIndex($table, 'sale_payments_created_idx');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $this->safeDropIndex($table, 'purchases_status_date_idx');
            $this->safeDropIndex($table, 'purchases_branch_supplier_idx');
        });

        Schema::table('customers', function (Blueprint $table) {
            $this->safeDropIndex($table, 'customers_active_branch_idx');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $this->safeDropIndex($table, 'suppliers_active_branch_idx');
        });
    }

    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $indexes = Schema::getIndexes($table);
            foreach ($indexes as $index) {
                if (strcasecmp($index['name'], $indexName) === 0) {
                    return true;
                }
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Safely drop an index if it exists
     */
    private function safeDropIndex(Blueprint $table, string $indexName): void
    {
        try {
            $table->dropIndex($indexName);
        } catch (\Exception $e) {
            // Index doesn't exist, continue
        }
    }
};
