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
        Schema::table('sales', function (Blueprint $table) {
            // Fix bug: These fields are referenced in queries but don't exist in migrations
            if (!Schema::hasColumn('sales', 'amount_paid')) {
                $table->decimal('amount_paid', 18, 4)->default(0)->after('paid_total')->comment('Total amount paid (alias for paid_total)');
            }
            if (!Schema::hasColumn('sales', 'amount_due')) {
                $table->decimal('amount_due', 18, 4)->default(0)->after('amount_paid')->comment('Total amount due (alias for due_total)');
            }
            
            // Payment tracking
            $table->string('payment_status')->default('unpaid')->after('amount_due')->comment('Payment status: unpaid, partial, paid, overpaid');
            $table->date('payment_due_date')->nullable()->after('payment_status')->comment('Payment due date');
            
            // Discount details
            $table->string('discount_type')->default('fixed')->after('discount_total')->comment('Discount type: fixed, percentage');
            $table->decimal('discount_value', 18, 4)->default(0)->after('discount_type')->comment('Discount value before calculation');
            
            // Shipping tracking
            $table->string('shipping_method')->nullable()->after('shipping_total')->comment('Shipping method/carrier');
            $table->string('tracking_number')->nullable()->after('shipping_method')->comment('Shipment tracking number');
            $table->date('expected_delivery_date')->nullable()->after('tracking_number')->comment('Expected delivery date');
            $table->date('actual_delivery_date')->nullable()->after('expected_delivery_date')->comment('Actual delivery date');
            
            // Additional metadata
            $table->string('sales_person')->nullable()->after('created_by')->comment('Sales person name or user ID');
            $table->text('internal_notes')->nullable()->after('notes')->comment('Internal notes not visible to customer');
            
            // Indexes
            $table->index('payment_status');
            $table->index('payment_due_date');
            $table->index(['branch_id', 'payment_status'], 'sales_branch_payment_idx');
        });

        Schema::table('purchases', function (Blueprint $table) {
            // Fix bug: These fields are referenced in queries but don't exist in migrations
            if (!Schema::hasColumn('purchases', 'amount_paid')) {
                $table->decimal('amount_paid', 18, 4)->default(0)->after('paid_total')->comment('Total amount paid (alias for paid_total)');
            }
            if (!Schema::hasColumn('purchases', 'amount_due')) {
                $table->decimal('amount_due', 18, 4)->default(0)->after('amount_paid')->comment('Total amount due (alias for due_total)');
            }
            
            // Payment tracking
            $table->string('payment_status')->default('unpaid')->after('amount_due')->comment('Payment status: unpaid, partial, paid');
            $table->date('payment_due_date')->nullable()->after('payment_status')->comment('Payment due date');
            
            // Discount details
            $table->string('discount_type')->default('fixed')->after('discount_total')->comment('Discount type: fixed, percentage');
            $table->decimal('discount_value', 18, 4)->default(0)->after('discount_type')->comment('Discount value before calculation');
            
            // Delivery tracking
            $table->date('expected_delivery_date')->nullable()->after('posted_at')->comment('Expected delivery date');
            $table->date('actual_delivery_date')->nullable()->after('expected_delivery_date')->comment('Actual delivery date');
            $table->string('delivery_status')->default('pending')->after('actual_delivery_date')->comment('Delivery status: pending, partial, completed');
            
            // Approval workflow
            $table->unsignedBigInteger('approved_by')->nullable()->after('delivery_status')->comment('User who approved the purchase');
            $table->timestamp('approved_at')->nullable()->after('approved_by')->comment('Purchase approval timestamp');
            
            // Additional metadata
            $table->string('requisition_number')->nullable()->after('reference_no')->comment('Related requisition number');
            $table->text('internal_notes')->nullable()->after('notes')->comment('Internal notes');
            
            // Indexes
            $table->index('payment_status');
            $table->index('payment_due_date');
            $table->index('delivery_status');
            $table->index(['branch_id', 'payment_status'], 'purch_branch_payment_idx');
            
            // Foreign key
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('sales_branch_payment_idx');
            $table->dropIndex(['payment_status']);
            $table->dropIndex(['payment_due_date']);
            $table->dropColumn([
                'amount_paid', 'amount_due', 'payment_status', 'payment_due_date',
                'discount_type', 'discount_value', 'shipping_method', 'tracking_number',
                'expected_delivery_date', 'actual_delivery_date', 'sales_person', 'internal_notes'
            ]);
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropIndex('purch_branch_payment_idx');
            $table->dropIndex(['payment_status']);
            $table->dropIndex(['payment_due_date']);
            $table->dropIndex(['delivery_status']);
            $table->dropColumn([
                'amount_paid', 'amount_due', 'payment_status', 'payment_due_date',
                'discount_type', 'discount_value', 'expected_delivery_date',
                'actual_delivery_date', 'delivery_status', 'approved_by', 'approved_at',
                'requisition_number', 'internal_notes'
            ]);
        });
    }
};
