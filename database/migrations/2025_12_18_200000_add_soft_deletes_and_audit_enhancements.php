<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Add soft deletes and audit trail enhancements
     */
    public function up(): void
    {
        // Add soft deletes to critical tables for data recovery
        $criticalTables = ['sales', 'purchases', 'customers', 'suppliers', 'products', 'rental_contracts'];
        
        foreach ($criticalTables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->softDeletes()->after('updated_at');
                });
            }
        }

        // Add additional tracking fields to customers
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                if (!Schema::hasColumn('customers', 'last_interaction_date')) {
                    $table->datetime('last_interaction_date')->nullable()->after('last_order_date');
                }
                if (!Schema::hasColumn('customers', 'customer_source')) {
                    $table->string('customer_source', 100)->nullable()->after('last_interaction_date');
                }
                if (!Schema::hasColumn('customers', 'lifetime_revenue')) {
                    $table->decimal('lifetime_revenue', 15, 4)->default(0)->after('customer_source');
                }
            });
        }

        // Add additional tracking fields to products
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (!Schema::hasColumn('products', 'warranty_period')) {
                    $table->integer('warranty_period')->nullable()->after('warranty_period_days')->comment('Warranty period in days');
                }
                if (!Schema::hasColumn('products', 'hs_code')) {
                    $table->string('hs_code', 50)->nullable()->after('origin_country')->comment('Harmonized System code for customs');
                }
            });
        }

        // Add additional tracking fields to sales
        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                if (!Schema::hasColumn('sales', 'estimated_profit_margin')) {
                    $table->decimal('estimated_profit_margin', 8, 4)->nullable()->after('grand_total')->comment('Estimated profit margin percentage');
                }
                if (!Schema::hasColumn('sales', 'shipping_carrier')) {
                    $table->string('shipping_carrier', 100)->nullable()->after('shipping_method');
                }
            });
        }

        // Add additional tracking fields to purchases
        if (Schema::hasTable('purchases')) {
            Schema::table('purchases', function (Blueprint $table) {
                if (!Schema::hasColumn('purchases', 'estimated_profit_margin')) {
                    $table->decimal('estimated_profit_margin', 8, 4)->nullable()->after('grand_total')->comment('Estimated profit margin percentage');
                }
                if (!Schema::hasColumn('purchases', 'shipping_carrier')) {
                    $table->string('shipping_carrier', 100)->nullable()->after('shipping_method');
                }
            });
        }

        // Add composite indexes for performance optimization
        if (Schema::hasTable('sales')) {
            $schemaBuilder = Schema::getConnection()->getSchemaBuilder();
            $indexes = $schemaBuilder->getIndexes('sales');
            $indexNames = array_column($indexes, 'name');
            
            if (!in_array('idx_sales_status_payment', $indexNames)) {
                Schema::table('sales', function (Blueprint $table) {
                    $table->index(['status', 'payment_status'], 'idx_sales_status_payment');
                });
            }
            
            if (!in_array('idx_sales_customer_date', $indexNames)) {
                Schema::table('sales', function (Blueprint $table) {
                    $table->index(['customer_id', 'posted_at'], 'idx_sales_customer_date');
                });
            }
        }

        if (Schema::hasTable('purchases')) {
            $schemaBuilder = Schema::getConnection()->getSchemaBuilder();
            $indexes = $schemaBuilder->getIndexes('purchases');
            $indexNames = array_column($indexes, 'name');
            
            if (!in_array('idx_purchases_status_payment', $indexNames)) {
                Schema::table('purchases', function (Blueprint $table) {
                    $table->index(['status', 'payment_status'], 'idx_purchases_status_payment');
                });
            }
            
            if (!in_array('idx_purchases_supplier_date', $indexNames)) {
                Schema::table('purchases', function (Blueprint $table) {
                    $table->index(['supplier_id', 'posted_at'], 'idx_purchases_supplier_date');
                });
            }
        }

        if (Schema::hasTable('products')) {
            $schemaBuilder = Schema::getConnection()->getSchemaBuilder();
            $indexes = $schemaBuilder->getIndexes('products');
            $indexNames = array_column($indexes, 'name');
            
            if (!in_array('idx_products_stock_alert', $indexNames)) {
                Schema::table('products', function (Blueprint $table) {
                    $table->index(['stock_quantity', 'min_stock'], 'idx_products_stock_alert');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove composite indexes
        if (Schema::hasTable('sales')) {
            $schemaBuilder = Schema::getConnection()->getSchemaBuilder();
            $indexes = $schemaBuilder->getIndexes('sales');
            $indexNames = array_column($indexes, 'name');
            
            Schema::table('sales', function (Blueprint $table) use ($indexNames) {
                if (in_array('idx_sales_status_payment', $indexNames)) {
                    $table->dropIndex('idx_sales_status_payment');
                }
                if (in_array('idx_sales_customer_date', $indexNames)) {
                    $table->dropIndex('idx_sales_customer_date');
                }
            });
        }

        if (Schema::hasTable('purchases')) {
            $schemaBuilder = Schema::getConnection()->getSchemaBuilder();
            $indexes = $schemaBuilder->getIndexes('purchases');
            $indexNames = array_column($indexes, 'name');
            
            Schema::table('purchases', function (Blueprint $table) use ($indexNames) {
                if (in_array('idx_purchases_status_payment', $indexNames)) {
                    $table->dropIndex('idx_purchases_status_payment');
                }
                if (in_array('idx_purchases_supplier_date', $indexNames)) {
                    $table->dropIndex('idx_purchases_supplier_date');
                }
            });
        }

        if (Schema::hasTable('products')) {
            $schemaBuilder = Schema::getConnection()->getSchemaBuilder();
            $indexes = $schemaBuilder->getIndexes('products');
            $indexNames = array_column($indexes, 'name');
            
            Schema::table('products', function (Blueprint $table) use ($indexNames) {
                if (in_array('idx_products_stock_alert', $indexNames)) {
                    $table->dropIndex('idx_products_stock_alert');
                }
            });
        }

        // Remove additional tracking fields
        if (Schema::hasTable('purchases') && Schema::hasColumn('purchases', 'shipping_carrier')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->dropColumn(['estimated_profit_margin', 'shipping_carrier']);
            });
        }

        if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'shipping_carrier')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropColumn(['estimated_profit_margin', 'shipping_carrier']);
            });
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'warranty_period')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn(['warranty_period', 'hs_code']);
            });
        }

        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'last_interaction_date')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn(['last_interaction_date', 'customer_source', 'lifetime_revenue']);
            });
        }

        // Remove soft deletes
        $criticalTables = ['sales', 'purchases', 'customers', 'suppliers', 'products', 'rental_contracts'];
        
        foreach ($criticalTables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropSoftDeletes();
                });
            }
        }
    }
};
