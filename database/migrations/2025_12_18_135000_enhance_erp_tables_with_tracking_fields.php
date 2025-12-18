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
        if (Schema::hasTable('sales') && !Schema::hasColumn('sales', 'delivery_date')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->date('delivery_date')->nullable()->after('posted_at');
                $table->string('shipping_method')->nullable()->after('delivery_date');
                $table->text('customer_notes')->nullable()->after('notes');
                $table->text('internal_notes')->nullable()->after('customer_notes');
            });
        }

        // Enhance purchases table
        if (Schema::hasTable('purchases') && !Schema::hasColumn('purchases', 'expected_delivery_date')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->date('expected_delivery_date')->nullable()->after('posted_at');
                $table->date('actual_delivery_date')->nullable()->after('expected_delivery_date');
                $table->string('shipping_method')->nullable()->after('actual_delivery_date');
                $table->text('supplier_notes')->nullable()->after('notes');
            });
        }

        // Enhance products table
        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'reorder_point')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('reorder_point', 10, 2)->nullable()->after('min_stock');
                $table->decimal('max_stock', 10, 2)->nullable()->after('reorder_point');
                $table->decimal('lead_time_days', 5, 1)->nullable()->after('max_stock');
                $table->string('location_code')->nullable()->after('lead_time_days');
                $table->string('shelf_life_days')->nullable()->after('location_code');
            });
        }

        // Enhance customers table
        if (Schema::hasTable('customers') && !Schema::hasColumn('customers', 'payment_terms_days')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->integer('payment_terms_days')->default(30)->after('credit_limit');
                $table->decimal('discount_percentage', 5, 2)->default(0)->after('payment_terms_days');
                $table->string('customer_group')->nullable()->after('discount_percentage');
                $table->string('preferred_payment_method')->nullable()->after('customer_group');
                $table->timestamp('last_order_date')->nullable()->after('preferred_payment_method');
            });
        }

        // Enhance suppliers table
        if (Schema::hasTable('suppliers') && !Schema::hasColumn('suppliers', 'payment_terms')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->string('payment_terms')->nullable()->after('tax_id');
                $table->integer('lead_time_days')->nullable()->after('payment_terms');
                $table->decimal('minimum_order_value', 10, 2)->nullable()->after('lead_time_days');
                $table->string('supplier_rating')->nullable()->after('minimum_order_value');
                $table->timestamp('last_purchase_date')->nullable()->after('supplier_rating');
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
        // Remove added columns
        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropColumn(['delivery_date', 'shipping_method', 'customer_notes', 'internal_notes']);
            });
        }

        if (Schema::hasTable('purchases')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->dropColumn(['expected_delivery_date', 'actual_delivery_date', 'shipping_method', 'supplier_notes']);
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn(['reorder_point', 'max_stock', 'lead_time_days', 'location_code', 'shelf_life_days']);
            });
        }

        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn(['payment_terms_days', 'discount_percentage', 'customer_group', 'preferred_payment_method', 'last_order_date']);
            });
        }

        if (Schema::hasTable('suppliers')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->dropColumn(['payment_terms', 'lead_time_days', 'minimum_order_value', 'supplier_rating', 'last_purchase_date']);
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
