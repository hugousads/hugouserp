<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add missing fields to sales and purchases tables.
     * 
     * BUG FIXES:
     * - Add amount_paid and amount_due columns that are referenced in models but don't exist
     * - Add payment_status for better tracking
     * - Add discount_type to distinguish between percentage and fixed discounts
     * - Add shipping tracking fields
     */
    public function up(): void
    {
        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                // Fix bug: These fields are referenced in queries but don't exist in migrations
                if (!Schema::hasColumn('sales', 'amount_paid')) {
                    $table->decimal('amount_paid', 18, 4)->default(0)->after('paid_total')->comment('Total amount paid (alias for paid_total)');
                }
                if (!Schema::hasColumn('sales', 'amount_due')) {
                    $table->decimal('amount_due', 18, 4)->default(0)->after('amount_paid')->comment('Total amount due (alias for due_total)');
                }
                
                // Payment tracking
                if (!Schema::hasColumn('sales', 'payment_status')) {
                    $table->string('payment_status')->default('unpaid')->after('amount_due')->comment('Payment status: unpaid, partial, paid, overpaid');
                }
                if (!Schema::hasColumn('sales', 'payment_due_date')) {
                    $table->date('payment_due_date')->nullable()->after('payment_status')->comment('Payment due date');
                }
                
                // Discount details
                if (!Schema::hasColumn('sales', 'discount_type')) {
                    $table->string('discount_type')->default('fixed')->after('discount_total')->comment('Discount type: fixed, percentage');
                }
                if (!Schema::hasColumn('sales', 'discount_value')) {
                    $table->decimal('discount_value', 18, 4)->default(0)->after('discount_type')->comment('Discount value before calculation');
                }
                
                // Shipping tracking
                if (!Schema::hasColumn('sales', 'shipping_method')) {
                    $table->string('shipping_method')->nullable()->after('shipping_total')->comment('Shipping method/carrier');
                }
                if (!Schema::hasColumn('sales', 'tracking_number')) {
                    $table->string('tracking_number')->nullable()->after('shipping_method')->comment('Shipment tracking number');
                }
                if (!Schema::hasColumn('sales', 'expected_delivery_date')) {
                    $table->date('expected_delivery_date')->nullable()->after('tracking_number')->comment('Expected delivery date');
                }
                if (!Schema::hasColumn('sales', 'actual_delivery_date')) {
                    $table->date('actual_delivery_date')->nullable()->after('expected_delivery_date')->comment('Actual delivery date');
                }
                
                // Additional metadata
                if (!Schema::hasColumn('sales', 'sales_person')) {
                    $table->string('sales_person')->nullable()->after('created_by')->comment('Sales person name or user ID');
                }
                if (!Schema::hasColumn('sales', 'internal_notes')) {
                    $table->text('internal_notes')->nullable()->after('notes')->comment('Internal notes not visible to customer');
                }
                
                // Indexes
                if (!$this->indexExists('sales', 'sales_payment_status_index')) {
                    $table->index('payment_status');
                }
                if (!$this->indexExists('sales', 'sales_payment_due_date_index')) {
                    $table->index('payment_due_date');
                }
                if (!$this->indexExists('sales', 'sales_branch_payment_idx')) {
                    $table->index(['branch_id', 'payment_status'], 'sales_branch_payment_idx');
                }
            });
        }

        if (Schema::hasTable('purchases')) {
            Schema::table('purchases', function (Blueprint $table) {
                // Fix bug: These fields are referenced in queries but don't exist in migrations
                if (!Schema::hasColumn('purchases', 'amount_paid')) {
                    $table->decimal('amount_paid', 18, 4)->default(0)->after('paid_total')->comment('Total amount paid (alias for paid_total)');
                }
                if (!Schema::hasColumn('purchases', 'amount_due')) {
                    $table->decimal('amount_due', 18, 4)->default(0)->after('amount_paid')->comment('Total amount due (alias for due_total)');
                }
                
                // Payment tracking
                if (!Schema::hasColumn('purchases', 'payment_status')) {
                    $table->string('payment_status')->default('unpaid')->after('amount_due')->comment('Payment status: unpaid, partial, paid');
                }
                if (!Schema::hasColumn('purchases', 'payment_due_date')) {
                    $table->date('payment_due_date')->nullable()->after('payment_status')->comment('Payment due date');
                }
                
                // Discount details
                if (!Schema::hasColumn('purchases', 'discount_type')) {
                    $table->string('discount_type')->default('fixed')->after('discount_total')->comment('Discount type: fixed, percentage');
                }
                if (!Schema::hasColumn('purchases', 'discount_value')) {
                    $table->decimal('discount_value', 18, 4)->default(0)->after('discount_type')->comment('Discount value before calculation');
                }
                
                // Delivery tracking
                if (!Schema::hasColumn('purchases', 'expected_delivery_date')) {
                    $table->date('expected_delivery_date')->nullable()->after('posted_at')->comment('Expected delivery date');
                }
                if (!Schema::hasColumn('purchases', 'actual_delivery_date')) {
                    $table->date('actual_delivery_date')->nullable()->after('expected_delivery_date')->comment('Actual delivery date');
                }
                if (!Schema::hasColumn('purchases', 'delivery_status')) {
                    $table->string('delivery_status')->default('pending')->after('actual_delivery_date')->comment('Delivery status: pending, partial, completed');
                }
                
                // Approval workflow
                if (!Schema::hasColumn('purchases', 'approved_by')) {
                    $table->unsignedBigInteger('approved_by')->nullable()->after('delivery_status')->comment('User who approved the purchase');
                }
                if (!Schema::hasColumn('purchases', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable()->after('approved_by')->comment('Purchase approval timestamp');
                }
                
                // Additional metadata
                if (!Schema::hasColumn('purchases', 'requisition_number')) {
                    $table->string('requisition_number')->nullable()->after('reference_no')->comment('Related requisition number');
                }
                if (!Schema::hasColumn('purchases', 'internal_notes')) {
                    $table->text('internal_notes')->nullable()->after('notes')->comment('Internal notes');
                }
                
                // Indexes
                if (!$this->indexExists('purchases', 'purchases_payment_status_index')) {
                    $table->index('payment_status');
                }
                if (!$this->indexExists('purchases', 'purchases_payment_due_date_index')) {
                    $table->index('payment_due_date');
                }
                if (!$this->indexExists('purchases', 'purchases_delivery_status_index')) {
                    $table->index('delivery_status');
                }
                if (!$this->indexExists('purchases', 'purch_branch_payment_idx')) {
                    $table->index(['branch_id', 'payment_status'], 'purch_branch_payment_idx');
                }
                
                // Foreign key
                if (!$this->foreignKeyExists('purchases', 'purchases_approved_by_foreign')) {
                    $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
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

    /**
     * Check if a foreign key exists
     */
    protected function foreignKeyExists(string $table, string $foreignKey): bool
    {
        try {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $foreignKeys = $sm->listTableForeignKeys($table);
            foreach ($foreignKeys as $fk) {
                if ($fk->getName() === $foreignKey) {
                    return true;
                }
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            try {
                $table->dropIndex('sales_branch_payment_idx');
            } catch (\Exception $e) {
                // Index may not exist
            }
            try {
                $table->dropIndex(['payment_status']);
            } catch (\Exception $e) {
                // Index may not exist
            }
            try {
                $table->dropIndex(['payment_due_date']);
            } catch (\Exception $e) {
                // Index may not exist
            }
            if (Schema::hasColumn('sales', 'amount_paid')) {
                $table->dropColumn([
                    'amount_paid', 'amount_due', 'payment_status', 'payment_due_date',
                    'discount_type', 'discount_value', 'shipping_method', 'tracking_number',
                    'expected_delivery_date', 'actual_delivery_date', 'sales_person', 'internal_notes'
                ]);
            }
        });

        Schema::table('purchases', function (Blueprint $table) {
            try {
                $table->dropForeign(['approved_by']);
            } catch (\Exception $e) {
                // Foreign key may not exist
            }
            try {
                $table->dropIndex('purch_branch_payment_idx');
            } catch (\Exception $e) {
                // Index may not exist
            }
            try {
                $table->dropIndex(['payment_status']);
            } catch (\Exception $e) {
                // Index may not exist
            }
            try {
                $table->dropIndex(['payment_due_date']);
            } catch (\Exception $e) {
                // Index may not exist
            }
            try {
                $table->dropIndex(['delivery_status']);
            } catch (\Exception $e) {
                // Index may not exist
            }
            if (Schema::hasColumn('purchases', 'amount_paid')) {
                $table->dropColumn([
                    'amount_paid', 'amount_due', 'payment_status', 'payment_due_date',
                    'discount_type', 'discount_value', 'expected_delivery_date',
                    'actual_delivery_date', 'delivery_status', 'approved_by', 'approved_at',
                    'requisition_number', 'internal_notes'
                ]);
            }
        });
    }
};
