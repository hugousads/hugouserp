<?php

declare(strict_types=1);

/**
 * Performance Indexes Migration
 * 
 * Adds optimized indexes for frequently queried columns
 * to improve system performance.
 * 
 * Safe to run on fresh database or existing data.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations
     */
    public function up(): void
    {
        // Sales indexes - high traffic table
        if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'status')) {
            Schema::table('sales', function (Blueprint $table) {
                // Index for dashboard queries (today's sales by branch)
                $this->addIndexIfNotExists($table, 'sales', ['branch_id', 'sale_date', 'status'], 'idx_sales_branch_date_status');
                
                // Index for customer history
                $this->addIndexIfNotExists($table, 'sales', ['customer_id', 'created_at'], 'idx_sales_customer_created');
                
                // Index for payment status queries
                $this->addIndexIfNotExists($table, 'sales', ['payment_status', 'due_date'], 'idx_sales_payment_due');
            });
        }

        // Products indexes
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'sku')) {
            Schema::table('products', function (Blueprint $table) {
                // Index for SKU lookups
                $this->addIndexIfNotExists($table, 'products', ['sku'], 'idx_products_sku');
                
                // Index for category filtering
                $this->addIndexIfNotExists($table, 'products', ['category_id', 'is_active'], 'idx_products_category_active');
                
                // Index for low stock queries
                if (Schema::hasColumn('products', 'alert_quantity')) {
                    $this->addIndexIfNotExists($table, 'products', ['is_active', 'track_stock_alerts'], 'idx_products_active_track');
                }
            });
        }

        // Stock movements indexes - high volume table
        if (Schema::hasTable('stock_movements') && Schema::hasColumn('stock_movements', 'product_id')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                // Index for product history
                $this->addIndexIfNotExists($table, 'stock_movements', ['product_id', 'created_at'], 'idx_stock_product_created');
                
                // Index for warehouse queries
                $this->addIndexIfNotExists($table, 'stock_movements', ['warehouse_id', 'movement_type', 'created_at'], 'idx_stock_warehouse_type_date');
            });
        }

        // Customers indexes
        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'phone')) {
            Schema::table('customers', function (Blueprint $table) {
                // Index for phone lookup
                $this->addIndexIfNotExists($table, 'customers', ['phone'], 'idx_customers_phone');
                
                // Index for branch + active queries
                $this->addIndexIfNotExists($table, 'customers', ['branch_id', 'is_active'], 'idx_customers_branch_active');
            });
        }

        // Purchases indexes
        if (Schema::hasTable('purchases') && Schema::hasColumn('purchases', 'supplier_id')) {
            Schema::table('purchases', function (Blueprint $table) {
                // Index for supplier history
                $this->addIndexIfNotExists($table, 'purchases', ['supplier_id', 'purchase_date'], 'idx_purchases_supplier_date');
                
                // Index for pending payments
                $this->addIndexIfNotExists($table, 'purchases', ['payment_status', 'due_date'], 'idx_purchases_payment_due');
            });
        }

        // Audit logs indexes - for compliance queries
        if (Schema::hasTable('audit_logs') && Schema::hasColumn('audit_logs', 'user_id')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                // Index for user activity reports
                $this->addIndexIfNotExists($table, 'audit_logs', ['user_id', 'action', 'created_at'], 'idx_audit_user_action_date');
                
                // Index for module activity
                if (Schema::hasColumn('audit_logs', 'module_key')) {
                    $this->addIndexIfNotExists($table, 'audit_logs', ['module_key', 'created_at'], 'idx_audit_module_date');
                }
            });
        }

        // POS sessions indexes
        if (Schema::hasTable('pos_sessions') && Schema::hasColumn('pos_sessions', 'user_id')) {
            Schema::table('pos_sessions', function (Blueprint $table) {
                // Index for open sessions
                if (Schema::hasColumn('pos_sessions', 'status')) {
                    $this->addIndexIfNotExists($table, 'pos_sessions', ['branch_id', 'status'], 'idx_pos_branch_status');
                }
                
                // Index for user sessions
                $this->addIndexIfNotExists($table, 'pos_sessions', ['user_id', 'opened_at'], 'idx_pos_user_opened');
            });
        }

        // Journal entries indexes - for accounting reports
        if (Schema::hasTable('journal_entries') && Schema::hasColumn('journal_entries', 'entry_date')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                // Index for date range queries
                $this->addIndexIfNotExists($table, 'journal_entries', ['entry_date', 'is_posted'], 'idx_journal_date_posted');
                
                // Index for account queries
                if (Schema::hasColumn('journal_entries', 'branch_id')) {
                    $this->addIndexIfNotExists($table, 'journal_entries', ['branch_id', 'entry_date'], 'idx_journal_branch_date');
                }
            });
        }

        // Notifications indexes - for performance
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                // Index for unread notifications query
                $this->addIndexIfNotExists($table, 'notifications', ['notifiable_id', 'read_at', 'created_at'], 'idx_notif_user_read_created');
            });
        }
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        $indexesToDrop = [
            'sales' => ['idx_sales_branch_date_status', 'idx_sales_customer_created', 'idx_sales_payment_due'],
            'products' => ['idx_products_sku', 'idx_products_category_active', 'idx_products_active_track'],
            'stock_movements' => ['idx_stock_product_created', 'idx_stock_warehouse_type_date'],
            'customers' => ['idx_customers_phone', 'idx_customers_branch_active'],
            'purchases' => ['idx_purchases_supplier_date', 'idx_purchases_payment_due'],
            'audit_logs' => ['idx_audit_user_action_date', 'idx_audit_module_date'],
            'pos_sessions' => ['idx_pos_branch_status', 'idx_pos_user_opened'],
            'journal_entries' => ['idx_journal_date_posted', 'idx_journal_branch_date'],
            'notifications' => ['idx_notif_user_read_created'],
        ];

        foreach ($indexesToDrop as $tableName => $indexes) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($indexes) {
                    foreach ($indexes as $indexName) {
                        try {
                            $table->dropIndex($indexName);
                        } catch (\Throwable) {
                            // Index may not exist, ignore
                        }
                    }
                });
            }
        }
    }

    /**
     * Add index if it doesn't already exist
     */
    private function addIndexIfNotExists(Blueprint $table, string $tableName, array $columns, string $indexName): void
    {
        try {
            // Check if index exists using database inspection
            $indexes = Schema::getIndexes($tableName);
            $existingIndexNames = array_column($indexes, 'name');
            
            if (!in_array($indexName, $existingIndexNames)) {
                $table->index($columns, $indexName);
            }
        } catch (\Throwable) {
            // If we can't check, try to add (may fail silently if exists)
            try {
                $table->index($columns, $indexName);
            } catch (\Throwable) {
                // Index already exists, ignore
            }
        }
    }
};
