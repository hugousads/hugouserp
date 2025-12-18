<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Enhance sales table
        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                // Only add columns that don't exist yet
                // Use 'posted_at' as reference since it's in the original table
                if (!Schema::hasColumn('sales', 'delivery_date')) {
                    $table->date('delivery_date')->nullable()->after('posted_at');
                }
                if (!Schema::hasColumn('sales', 'shipping_method')) {
                    $table->string('shipping_method')->nullable()->after('posted_at');
                }
                if (!Schema::hasColumn('sales', 'customer_notes')) {
                    $table->text('customer_notes')->nullable()->after('notes');
                }
                if (!Schema::hasColumn('sales', 'internal_notes')) {
                    $table->text('internal_notes')->nullable()->after('notes');
                }
            });
        }

        // Enhance purchases table
        if (Schema::hasTable('purchases')) {
            Schema::table('purchases', function (Blueprint $table) {
                // Only add columns that don't exist yet
                // Use 'posted_at' as reference since it's in the original table
                if (!Schema::hasColumn('purchases', 'expected_delivery_date')) {
                    $table->date('expected_delivery_date')->nullable()->after('posted_at');
                }
                if (!Schema::hasColumn('purchases', 'actual_delivery_date')) {
                    $table->date('actual_delivery_date')->nullable()->after('posted_at');
                }
                if (!Schema::hasColumn('purchases', 'shipping_method')) {
                    $table->string('shipping_method')->nullable()->after('posted_at');
                }
                if (!Schema::hasColumn('purchases', 'supplier_notes')) {
                    $table->text('supplier_notes')->nullable()->after('notes');
                }
            });
        }

        // Enhance products table
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                // Note: reorder_point already exists in the original products table creation
                // Only add columns that don't exist yet
                if (!Schema::hasColumn('products', 'max_stock')) {
                    $table->decimal('max_stock', 10, 2)->nullable()->after('reorder_point');
                }
                if (!Schema::hasColumn('products', 'lead_time_days')) {
                    $table->decimal('lead_time_days', 5, 1)->nullable()->after('max_stock');
                }
                if (!Schema::hasColumn('products', 'location_code')) {
                    $table->string('location_code')->nullable()->after('lead_time_days');
                }
                // Note: shelf_life_days already added by migration 2025_12_18_000003
                // Skipping to avoid conflicts
            });
        }

        // Enhance customers table
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                // Only add columns that don't exist yet
                // Use 'credit_limit' and 'status' as stable reference columns from original table
                if (!Schema::hasColumn('customers', 'payment_terms_days')) {
                    $table->integer('payment_terms_days')->default(30)->after('credit_limit');
                }
                if (!Schema::hasColumn('customers', 'customer_group')) {
                    $table->string('customer_group')->nullable()->after('status');
                }
                if (!Schema::hasColumn('customers', 'preferred_payment_method')) {
                    $table->string('preferred_payment_method')->nullable()->after('status');
                }
                if (!Schema::hasColumn('customers', 'last_order_date')) {
                    $table->timestamp('last_order_date')->nullable()->after('status');
                }
            });
        }

        // Enhance suppliers table
        if (Schema::hasTable('suppliers')) {
            Schema::table('suppliers', function (Blueprint $table) {
                // Only add columns that don't exist yet
                // Use 'is_active' as stable reference column from original table
                if (!Schema::hasColumn('suppliers', 'minimum_order_value')) {
                    $table->decimal('minimum_order_value', 10, 2)->nullable()->after('is_active');
                }
                if (!Schema::hasColumn('suppliers', 'supplier_rating')) {
                    $table->string('supplier_rating')->nullable()->after('is_active');
                }
                if (!Schema::hasColumn('suppliers', 'last_purchase_date')) {
                    $table->timestamp('last_purchase_date')->nullable()->after('is_active');
                }
            });
        }

        // Enhance rental_contracts table
        if (Schema::hasTable('rental_contracts') && !Schema::hasColumn('rental_contracts', 'renewal_notice_days')) {
            Schema::table('rental_contracts', function (Blueprint $table) {
                $table->integer('renewal_notice_days')->default(30)->after('end_date');
                $table->boolean('auto_renew')->default(false)->after('renewal_notice_days');
                $table->integer('renewal_term_months')->nullable()->after('auto_renew');
                $table->decimal('deposit_refunded', 10, 2)->nullable()->after('deposit');
                $table->date('deposit_refund_date')->nullable()->after('deposit_refunded');
            });
        }

        // Enhance hr_employees table  
        if (Schema::hasTable('hr_employees') && !Schema::hasColumn('hr_employees', 'emergency_contact_name')) {
            Schema::table('hr_employees', function (Blueprint $table) {
                $table->string('emergency_contact_name')->nullable()->after('phone');
                $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
                $table->string('emergency_contact_relation')->nullable()->after('emergency_contact_phone');
                $table->date('contract_start_date')->nullable()->after('hire_date');
                $table->date('contract_end_date')->nullable()->after('contract_start_date');
                $table->string('work_permit_number')->nullable()->after('contract_end_date');
                $table->date('work_permit_expiry')->nullable()->after('work_permit_number');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove added columns - only drop if they exist
        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                $columnsToDropSales = [];
                if (Schema::hasColumn('sales', 'delivery_date')) {
                    $columnsToDropSales[] = 'delivery_date';
                }
                if (Schema::hasColumn('sales', 'customer_notes')) {
                    $columnsToDropSales[] = 'customer_notes';
                }
                // Note: shipping_method and internal_notes are added by earlier migration
                // so we don't drop them here to avoid breaking the rollback chain
                if (!empty($columnsToDropSales)) {
                    $table->dropColumn($columnsToDropSales);
                }
            });
        }

        if (Schema::hasTable('purchases')) {
            Schema::table('purchases', function (Blueprint $table) {
                $columnsToDropPurchases = [];
                if (Schema::hasColumn('purchases', 'supplier_notes')) {
                    $columnsToDropPurchases[] = 'supplier_notes';
                }
                // Note: expected_delivery_date, actual_delivery_date are added by earlier migration
                // so we don't drop them here to avoid breaking the rollback chain
                if (!empty($columnsToDropPurchases)) {
                    $table->dropColumn($columnsToDropPurchases);
                }
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                $columnsToDropProducts = [];
                // Note: reorder_point is from original table, don't drop it
                if (Schema::hasColumn('products', 'max_stock')) {
                    $columnsToDropProducts[] = 'max_stock';
                }
                if (Schema::hasColumn('products', 'lead_time_days')) {
                    $columnsToDropProducts[] = 'lead_time_days';
                }
                if (Schema::hasColumn('products', 'location_code')) {
                    $columnsToDropProducts[] = 'location_code';
                }
                // Note: shelf_life_days is from earlier migration, don't drop it
                if (!empty($columnsToDropProducts)) {
                    $table->dropColumn($columnsToDropProducts);
                }
            });
        }

        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                $columnsToDropCustomers = [];
                if (Schema::hasColumn('customers', 'payment_terms_days')) {
                    $columnsToDropCustomers[] = 'payment_terms_days';
                }
                if (Schema::hasColumn('customers', 'customer_group')) {
                    $columnsToDropCustomers[] = 'customer_group';
                }
                if (Schema::hasColumn('customers', 'preferred_payment_method')) {
                    $columnsToDropCustomers[] = 'preferred_payment_method';
                }
                if (Schema::hasColumn('customers', 'last_order_date')) {
                    $columnsToDropCustomers[] = 'last_order_date';
                }
                // Note: discount_percentage is from earlier migration, don't drop it
                if (!empty($columnsToDropCustomers)) {
                    $table->dropColumn($columnsToDropCustomers);
                }
            });
        }

        if (Schema::hasTable('suppliers')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $columnsToDropSuppliers = [];
                if (Schema::hasColumn('suppliers', 'minimum_order_value')) {
                    $columnsToDropSuppliers[] = 'minimum_order_value';
                }
                if (Schema::hasColumn('suppliers', 'supplier_rating')) {
                    $columnsToDropSuppliers[] = 'supplier_rating';
                }
                if (Schema::hasColumn('suppliers', 'last_purchase_date')) {
                    $columnsToDropSuppliers[] = 'last_purchase_date';
                }
                // Note: payment_terms and lead_time_days are from earlier migration, don't drop them
                if (!empty($columnsToDropSuppliers)) {
                    $table->dropColumn($columnsToDropSuppliers);
                }
            });
        }

        if (Schema::hasTable('rental_contracts')) {
            Schema::table('rental_contracts', function (Blueprint $table) {
                $table->dropColumn(['renewal_notice_days', 'auto_renew', 'renewal_term_months', 'deposit_refunded', 'deposit_refund_date']);
            });
        }

        if (Schema::hasTable('hr_employees')) {
            Schema::table('hr_employees', function (Blueprint $table) {
                $table->dropColumn(['emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation', 'contract_start_date', 'contract_end_date', 'work_permit_number', 'work_permit_expiry']);
            });
        }
    }
};
