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
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                // Financial tracking
                if (!Schema::hasColumn('customers', 'balance')) {
                    $table->decimal('balance', 18, 4)->default(0)->after('loyalty_points')->comment('Current balance (positive = they owe us)');
                }
                if (!Schema::hasColumn('customers', 'credit_limit')) {
                    $table->decimal('credit_limit', 18, 4)->default(0)->after('balance')->comment('Maximum credit limit');
                }
                if (!Schema::hasColumn('customers', 'total_purchases')) {
                    $table->decimal('total_purchases', 18, 4)->default(0)->after('credit_limit')->comment('Lifetime purchase total');
                }
                if (!Schema::hasColumn('customers', 'discount_percentage')) {
                    $table->decimal('discount_percentage', 8, 4)->default(0)->after('total_purchases')->comment('Default discount percentage');
                }
                
                // Payment terms
                if (!Schema::hasColumn('customers', 'payment_terms')) {
                    $table->string('payment_terms')->default('immediate')->after('discount_percentage')->comment('Payment terms: immediate, net15, net30, net60, net90');
                }
                if (!Schema::hasColumn('customers', 'payment_due_days')) {
                    $table->integer('payment_due_days')->default(0)->after('payment_terms')->comment('Number of days for payment due');
                }
                
                // Currency preference
                if (!Schema::hasColumn('customers', 'preferred_currency')) {
                    $table->string('preferred_currency', 3)->default('EGP')->after('payment_due_days')->comment('Preferred currency code');
                }
                
                // Additional contact info
                if (!Schema::hasColumn('customers', 'website')) {
                    $table->string('website')->nullable()->after('preferred_currency')->comment('Customer website');
                }
                if (!Schema::hasColumn('customers', 'fax')) {
                    $table->string('fax')->nullable()->after('website')->comment('Fax number');
                }
                
                // Credit status
                if (!Schema::hasColumn('customers', 'credit_hold')) {
                    $table->boolean('credit_hold')->default(false)->after('fax')->comment('Is customer on credit hold');
                }
                if (!Schema::hasColumn('customers', 'credit_hold_reason')) {
                    $table->text('credit_hold_reason')->nullable()->after('credit_hold')->comment('Reason for credit hold');
                }
                
                // Indexes for performance
                if (!$this->indexExists('customers', 'customers_balance_index')) {
                    $table->index('balance');
                }
                if (!$this->indexExists('customers', 'customers_credit_hold_index')) {
                    $table->index('credit_hold');
                }
                if (!$this->indexExists('customers', 'cust_branch_balance_idx')) {
                    $table->index(['branch_id', 'balance'], 'cust_branch_balance_idx');
                }
            });
        }

        if (Schema::hasTable('suppliers')) {
            Schema::table('suppliers', function (Blueprint $table) {
                // Financial tracking
                if (!Schema::hasColumn('suppliers', 'balance')) {
                    $table->decimal('balance', 18, 4)->default(0)->after('is_active')->comment('Current balance (positive = we owe them)');
                }
                if (!Schema::hasColumn('suppliers', 'total_purchases')) {
                    $table->decimal('total_purchases', 18, 4)->default(0)->after('balance')->comment('Lifetime purchase total from this supplier');
                }
                if (!Schema::hasColumn('suppliers', 'average_lead_time_days')) {
                    $table->decimal('average_lead_time_days', 8, 2)->default(0)->after('total_purchases')->comment('Average delivery lead time in days');
                }
                
                // Payment terms
                if (!Schema::hasColumn('suppliers', 'payment_terms')) {
                    $table->string('payment_terms')->default('immediate')->after('average_lead_time_days')->comment('Payment terms: immediate, net15, net30, net60, net90');
                }
                if (!Schema::hasColumn('suppliers', 'payment_due_days')) {
                    $table->integer('payment_due_days')->default(0)->after('payment_terms')->comment('Number of days for payment due');
                }
                
                // Currency preference
                if (!Schema::hasColumn('suppliers', 'preferred_currency')) {
                    $table->string('preferred_currency', 3)->default('EGP')->after('payment_due_days')->comment('Preferred currency code');
                }
                
                // Performance metrics
                if (!Schema::hasColumn('suppliers', 'quality_rating')) {
                    $table->decimal('quality_rating', 3, 2)->default(0)->after('preferred_currency')->comment('Quality rating 0-5');
                }
                if (!Schema::hasColumn('suppliers', 'delivery_rating')) {
                    $table->decimal('delivery_rating', 3, 2)->default(0)->after('quality_rating')->comment('On-time delivery rating 0-5');
                }
                if (!Schema::hasColumn('suppliers', 'service_rating')) {
                    $table->decimal('service_rating', 3, 2)->default(0)->after('delivery_rating')->comment('Service rating 0-5');
                }
                if (!Schema::hasColumn('suppliers', 'total_orders')) {
                    $table->integer('total_orders')->default(0)->after('service_rating')->comment('Total number of orders placed');
                }
                
                // Contact info
                if (!Schema::hasColumn('suppliers', 'website')) {
                    $table->string('website')->nullable()->after('total_orders')->comment('Supplier website');
                }
                if (!Schema::hasColumn('suppliers', 'fax')) {
                    $table->string('fax')->nullable()->after('website')->comment('Fax number');
                }
                if (!Schema::hasColumn('suppliers', 'contact_person')) {
                    $table->string('contact_person')->nullable()->after('fax')->comment('Primary contact person name');
                }
                if (!Schema::hasColumn('suppliers', 'contact_person_phone')) {
                    $table->string('contact_person_phone')->nullable()->after('contact_person')->comment('Primary contact phone');
                }
                if (!Schema::hasColumn('suppliers', 'contact_person_email')) {
                    $table->string('contact_person_email')->nullable()->after('contact_person_phone')->comment('Primary contact email');
                }
                
                // Status
                if (!Schema::hasColumn('suppliers', 'is_approved')) {
                    $table->boolean('is_approved')->default(true)->after('contact_person_email')->comment('Is supplier approved for purchases');
                }
                if (!Schema::hasColumn('suppliers', 'notes')) {
                    $table->text('notes')->nullable()->after('is_approved')->comment('Additional notes about supplier');
                }
                
                // Indexes for performance
                if (!$this->indexExists('suppliers', 'suppliers_balance_index')) {
                    $table->index('balance');
                }
                if (!$this->indexExists('suppliers', 'supp_active_approved_idx')) {
                    $table->index(['is_active', 'is_approved'], 'supp_active_approved_idx');
                }
                if (!$this->indexExists('suppliers', 'supp_branch_balance_idx')) {
                    $table->index(['branch_id', 'balance'], 'supp_branch_balance_idx');
                }
            });
        }
    }

    /**
     * Check if an index exists
     */
    protected function indexExists(string $table, string $index): bool
    {
        try {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = $sm->listTableIndexes($table);
            return isset($indexes[$index]);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            try {
                $table->dropIndex('cust_branch_balance_idx');
            } catch (\Exception $e) {
                // Index may not exist
            }
            try {
                $table->dropIndex(['balance']);
            } catch (\Exception $e) {
                // Index may not exist
            }
            try {
                $table->dropIndex(['credit_hold']);
            } catch (\Exception $e) {
                // Index may not exist
            }
            if (Schema::hasColumn('customers', 'balance')) {
                $table->dropColumn([
                    'balance', 'credit_limit', 'total_purchases', 'discount_percentage',
                    'payment_terms', 'payment_due_days', 'preferred_currency',
                    'website', 'fax', 'credit_hold', 'credit_hold_reason'
                ]);
            }
        });

        Schema::table('suppliers', function (Blueprint $table) {
            try {
                $table->dropIndex('supp_branch_balance_idx');
            } catch (\Exception $e) {
                // Index may not exist
            }
            try {
                $table->dropIndex('supp_active_approved_idx');
            } catch (\Exception $e) {
                // Index may not exist
            }
            try {
                $table->dropIndex(['balance']);
            } catch (\Exception $e) {
                // Index may not exist
            }
            if (Schema::hasColumn('suppliers', 'balance')) {
                $table->dropColumn([
                    'balance', 'total_purchases', 'average_lead_time_days',
                    'payment_terms', 'payment_due_days', 'preferred_currency',
                    'quality_rating', 'delivery_rating', 'service_rating', 'total_orders',
                    'website', 'fax', 'contact_person', 'contact_person_phone',
                    'contact_person_email', 'is_approved', 'notes'
                ]);
            }
        });
    }
};
