<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add missing business-critical fields to customers and suppliers tables.
     * 
     * BUG FIXES:
     * - Add balance tracking for customers and suppliers
     * - Add credit limit management for customers
     * - Add payment terms for both
     * - Add discount percentage for customers
     * - Add currency preference
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Financial tracking
            $table->decimal('balance', 18, 4)->default(0)->after('loyalty_points')->comment('Current balance (positive = they owe us)');
            $table->decimal('credit_limit', 18, 4)->default(0)->after('balance')->comment('Maximum credit limit');
            $table->decimal('total_purchases', 18, 4)->default(0)->after('credit_limit')->comment('Lifetime purchase total');
            $table->decimal('discount_percentage', 8, 4)->default(0)->after('total_purchases')->comment('Default discount percentage');
            
            // Payment terms
            $table->string('payment_terms')->default('immediate')->after('discount_percentage')->comment('Payment terms: immediate, net15, net30, net60, net90');
            $table->integer('payment_due_days')->default(0)->after('payment_terms')->comment('Number of days for payment due');
            
            // Currency preference
            $table->string('preferred_currency', 3)->default('EGP')->after('payment_due_days')->comment('Preferred currency code');
            
            // Additional contact info
            $table->string('website')->nullable()->after('preferred_currency')->comment('Customer website');
            $table->string('fax')->nullable()->after('website')->comment('Fax number');
            
            // Credit status
            $table->boolean('credit_hold')->default(false)->after('fax')->comment('Is customer on credit hold');
            $table->text('credit_hold_reason')->nullable()->after('credit_hold')->comment('Reason for credit hold');
            
            // Indexes for performance
            $table->index('balance');
            $table->index('credit_hold');
            $table->index(['branch_id', 'balance'], 'cust_branch_balance_idx');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            // Financial tracking
            $table->decimal('balance', 18, 4)->default(0)->after('is_active')->comment('Current balance (positive = we owe them)');
            $table->decimal('total_purchases', 18, 4)->default(0)->after('balance')->comment('Lifetime purchase total from this supplier');
            $table->decimal('average_lead_time_days', 8, 2)->default(0)->after('total_purchases')->comment('Average delivery lead time in days');
            
            // Payment terms
            $table->string('payment_terms')->default('immediate')->after('average_lead_time_days')->comment('Payment terms: immediate, net15, net30, net60, net90');
            $table->integer('payment_due_days')->default(0)->after('payment_terms')->comment('Number of days for payment due');
            
            // Currency preference
            $table->string('preferred_currency', 3)->default('EGP')->after('payment_due_days')->comment('Preferred currency code');
            
            // Performance metrics
            $table->decimal('quality_rating', 3, 2)->default(0)->after('preferred_currency')->comment('Quality rating 0-5');
            $table->decimal('delivery_rating', 3, 2)->default(0)->after('quality_rating')->comment('On-time delivery rating 0-5');
            $table->decimal('service_rating', 3, 2)->default(0)->after('delivery_rating')->comment('Service rating 0-5');
            $table->integer('total_orders', )->default(0)->after('service_rating')->comment('Total number of orders placed');
            
            // Contact info
            $table->string('website')->nullable()->after('total_orders')->comment('Supplier website');
            $table->string('fax')->nullable()->after('website')->comment('Fax number');
            $table->string('contact_person')->nullable()->after('fax')->comment('Primary contact person name');
            $table->string('contact_person_phone')->nullable()->after('contact_person')->comment('Primary contact phone');
            $table->string('contact_person_email')->nullable()->after('contact_person_phone')->comment('Primary contact email');
            
            // Status
            $table->boolean('is_approved')->default(true)->after('contact_person_email')->comment('Is supplier approved for purchases');
            $table->text('notes')->nullable()->after('is_approved')->comment('Additional notes about supplier');
            
            // Indexes for performance
            $table->index('balance');
            $table->index(['is_active', 'is_approved'], 'supp_active_approved_idx');
            $table->index(['branch_id', 'balance'], 'supp_branch_balance_idx');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('cust_branch_balance_idx');
            $table->dropIndex(['balance']);
            $table->dropIndex(['credit_hold']);
            $table->dropColumn([
                'balance', 'credit_limit', 'total_purchases', 'discount_percentage',
                'payment_terms', 'payment_due_days', 'preferred_currency',
                'website', 'fax', 'credit_hold', 'credit_hold_reason'
            ]);
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex('supp_branch_balance_idx');
            $table->dropIndex('supp_active_approved_idx');
            $table->dropIndex(['balance']);
            $table->dropColumn([
                'balance', 'total_purchases', 'average_lead_time_days',
                'payment_terms', 'payment_due_days', 'preferred_currency',
                'quality_rating', 'delivery_rating', 'service_rating', 'total_orders',
                'website', 'fax', 'contact_person', 'contact_person_phone',
                'contact_person_email', 'is_approved', 'notes'
            ]);
        });
    }
};
