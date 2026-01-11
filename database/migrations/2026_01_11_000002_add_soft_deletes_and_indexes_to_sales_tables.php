<?php

declare(strict_types=1);

/**
 * Add soft deletes to sale_items and additional performance indexes
 * 
 * Fixes:
 * - Bug #3: Soft Delete Inconsistency - adds SoftDeletes to sale_items
 * - Bug #4: Missing Database Indexes - adds composite indexes for performance
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add soft deletes to sale_items for consistency with sales table
        Schema::table('sale_items', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add additional performance indexes to sales table
        Schema::table('sales', function (Blueprint $table) {
            // Note: idx_sales_customer_created already added in 2026_01_04_100001_add_performance_indexes.php
            
            // Composite index for warehouse sales queries
            $this->addIndexIfNotExists('sales', $table, ['warehouse_id', 'created_at'], 'idx_sales_warehouse_created');
        });

        // Add performance index to inventory_movements if table exists
        // Note: This table is created in a separate migration and may not exist in all environments
        if (Schema::hasTable('inventory_movements')) {
            Schema::table('inventory_movements', function (Blueprint $table) {
                $this->addIndexIfNotExists('inventory_movements', $table, ['branch_id', 'created_at'], 'idx_inv_movements_branch_created');
            });
        }
    }

    public function down(): void
    {
        // Remove soft deletes from sale_items
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        // Remove indexes from sales table
        Schema::table('sales', function (Blueprint $table) {
            $this->dropIndexIfExists('sales', $table, 'idx_sales_warehouse_created');
        });

        // Remove index from inventory_movements if table exists
        if (Schema::hasTable('inventory_movements')) {
            Schema::table('inventory_movements', function (Blueprint $table) {
                $this->dropIndexIfExists('inventory_movements', $table, 'idx_inv_movements_branch_created');
            });
        }
    }

    /**
     * Add index if it doesn't already exist
     */
    private function addIndexIfNotExists(string $tableName, Blueprint $table, array $columns, string $indexName): void
    {
        try {
            $indexes = Schema::getIndexes($tableName);
            $existingIndexNames = array_column($indexes, 'name');

            if (!in_array($indexName, $existingIndexNames)) {
                $table->index($columns, $indexName);
            }
        } catch (\Throwable) {
            // If we can't check, try to add it anyway
            try {
                $table->index($columns, $indexName);
            } catch (\Throwable) {
                // Index already exists or other error, ignore
            }
        }
    }

    /**
     * Drop index if it exists
     */
    private function dropIndexIfExists(string $tableName, Blueprint $table, string $indexName): void
    {
        try {
            $indexes = Schema::getIndexes($tableName);
            $existingIndexNames = array_column($indexes, 'name');

            if (in_array($indexName, $existingIndexNames)) {
                $table->dropIndex($indexName);
            }
        } catch (\Throwable) {
            // If we can't check, try to drop it anyway
            try {
                $table->dropIndex($indexName);
            } catch (\Throwable) {
                // Index doesn't exist or other error, ignore
            }
        }
    }
};
