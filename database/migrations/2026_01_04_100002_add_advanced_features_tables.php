<?php

declare(strict_types=1);

/**
 * Advanced Features Tables Migration
 * 
 * Adds comprehensive tables for:
 * - Sales Returns & Credit Notes
 * - Purchase Returns & Debit Notes  
 * - Enhanced Stock Transfers (approvals, documents, history)
 * - Complete Leave Management System
 * - Supplier Performance Tracking
 * 
 * MySQL 8.4 Optimized - Works with existing structure
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function setTableOptions(Blueprint $table): void
    {
        $table->engine = 'InnoDB';
        $table->charset = 'utf8mb4';
        $table->collation = 'utf8mb4_0900_ai_ci';
    }

    public function up(): void
    {
        // ===== SALES RETURNS MODULE =====
        
        // Sales Returns - Main return documents
        Schema::create('sales_returns', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->string('return_number', 50)->unique();
            $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->enum('return_type', ['full', 'partial'])->default('partial');
            $table->enum('status', ['pending', 'approved', 'rejected', 'completed', 'cancelled'])->default('pending')->index();
            $table->string('reason', 255)->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0)->index();
            $table->decimal('refund_amount', 15, 2)->default(0);
            $table->string('currency', 3)->default('EGP');
            $table->enum('refund_method', ['original', 'cash', 'bank_transfer', 'credit', 'store_credit'])->default('original');
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['branch_id', 'status', 'created_at']);
            $table->index(['customer_id', 'created_at']);
        });

        // Sales Return Items
        Schema::create('sales_return_items', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('sales_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->decimal('qty_returned', 15, 3)->default(0);
            $table->decimal('qty_original', 15, 3)->default(0);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->enum('condition', ['new', 'used', 'damaged', 'defective'])->default('new');
            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('restock')->default(true);
            $table->foreignId('restocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('restocked_at')->nullable();
            $table->timestamps();
            $table->index(['sales_return_id', 'product_id']);
        });

        // Credit Notes
        Schema::create('credit_notes', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->string('credit_note_number', 50)->unique();
            $table->foreignId('sales_return_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->enum('type', ['return', 'adjustment', 'discount', 'refund', 'other'])->default('return');
            $table->enum('status', ['draft', 'pending', 'approved', 'applied', 'cancelled'])->default('draft')->index();
            $table->decimal('amount', 15, 2)->default(0)->index();
            $table->string('currency', 3)->default('EGP');
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->date('issue_date')->nullable()->index();
            $table->date('applied_date')->nullable();
            $table->boolean('auto_apply')->default(true);
            $table->decimal('applied_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2)->default(0);
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('posted_to_accounting')->default(false)->index();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['branch_id', 'status', 'issue_date']);
            $table->index(['customer_id', 'status']);
        });

        // Credit Note Applications
        Schema::create('credit_note_applications', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('credit_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->restrictOnDelete();
            $table->decimal('applied_amount', 15, 2)->default(0);
            $table->date('application_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['credit_note_id', 'application_date']);
        });

        // Return Refunds
        Schema::create('return_refunds', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('sales_return_id')->constrained()->restrictOnDelete();
            $table->foreignId('credit_note_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->enum('refund_method', ['cash', 'bank_transfer', 'credit_card', 'store_credit', 'original_method'])->index();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('currency', 3)->default('EGP');
            $table->string('reference_number', 100)->nullable()->index();
            $table->string('transaction_id', 100)->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending')->index();
            $table->text('notes')->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('account_number', 50)->nullable();
            $table->string('card_last_four', 4)->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['sales_return_id', 'status']);
        });

        // ===== PURCHASE RETURNS MODULE =====
        
        // Purchase Returns
        Schema::create('purchase_returns', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->string('return_number', 50)->unique();
            $table->foreignId('purchase_id')->constrained('purchases')->restrictOnDelete();
            $table->foreignId('grn_id')->nullable()->constrained('goods_received_notes')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->enum('return_type', ['full', 'partial'])->default('partial');
            $table->enum('status', ['pending', 'approved', 'shipped', 'completed', 'cancelled'])->default('pending')->index();
            $table->string('reason', 255)->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0)->index();
            $table->decimal('expected_credit', 15, 2)->default(0);
            $table->string('currency', 3)->default('EGP');
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->date('return_date')->nullable()->index();
            $table->string('tracking_number', 100)->nullable();
            $table->string('courier_name', 100)->nullable();
            $table->date('shipped_date')->nullable();
            $table->date('received_by_supplier_date')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('shipped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['branch_id', 'status', 'return_date']);
            $table->index(['supplier_id', 'return_date']);
        });

        // Purchase Return Items
        Schema::create('purchase_return_items', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('purchase_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_item_id')->constrained('purchase_items')->restrictOnDelete();
            $table->foreignId('grn_item_id')->nullable()->constrained('grn_items')->nullOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->decimal('qty_returned', 15, 3)->default(0);
            $table->decimal('qty_original', 15, 3)->default(0);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->enum('condition', ['defective', 'damaged', 'wrong_item', 'excess', 'expired'])->default('defective');
            $table->string('batch_number', 100)->nullable();
            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('deduct_from_stock')->default(true);
            $table->foreignId('deducted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deducted_at')->nullable();
            $table->timestamps();
            $table->index(['purchase_return_id', 'product_id']);
        });

        // Debit Notes
        Schema::create('debit_notes', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->string('debit_note_number', 50)->unique();
            $table->foreignId('purchase_return_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('purchase_id')->constrained('purchases')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['return', 'adjustment', 'discount', 'damage', 'other'])->default('return');
            $table->enum('status', ['draft', 'pending', 'approved', 'applied', 'cancelled'])->default('draft')->index();
            $table->decimal('amount', 15, 2)->default(0)->index();
            $table->string('currency', 3)->default('EGP');
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->date('issue_date')->nullable()->index();
            $table->date('applied_date')->nullable();
            $table->boolean('auto_apply')->default(true);
            $table->decimal('applied_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2)->default(0);
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('posted_to_accounting')->default(false)->index();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['branch_id', 'status', 'issue_date']);
            $table->index(['supplier_id', 'status']);
        });

        // Supplier Performance Metrics
        Schema::create('supplier_performance_metrics', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('period', 20)->index(); // e.g., '2026-01', '2026-Q1'
            $table->integer('total_orders')->default(0);
            $table->integer('on_time_deliveries')->default(0);
            $table->integer('late_deliveries')->default(0);
            $table->decimal('on_time_delivery_rate', 5, 2)->default(0);
            $table->decimal('total_ordered_qty', 15, 3)->default(0);
            $table->decimal('total_received_qty', 15, 3)->default(0);
            $table->decimal('total_rejected_qty', 15, 3)->default(0);
            $table->decimal('quality_acceptance_rate', 5, 2)->default(100);
            $table->integer('total_returns')->default(0);
            $table->decimal('return_rate', 5, 2)->default(0);
            $table->decimal('total_purchase_value', 15, 2)->default(0);
            $table->decimal('average_order_value', 15, 2)->default(0);
            $table->decimal('average_lead_time_days', 10, 2)->default(0);
            $table->decimal('performance_score', 5, 2)->default(100);
            $table->text('notes')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();
            $table->unique(['supplier_id', 'branch_id', 'period']);
            $table->index(['branch_id', 'period']);
        });

        // ===== ENHANCED STOCK TRANSFERS =====
        
        // Stock Transfer Approvals
        Schema::create('stock_transfer_approvals', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('transfer_id')->constrained('transfers')->cascadeOnDelete();
            $table->integer('approval_level')->default(1);
            $table->foreignId('approver_id')->constrained('users')->restrictOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            $table->text('comments')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->index(['transfer_id', 'approval_level']);
            $table->index(['approver_id', 'status']);
        });

        // Stock Transfer Documents
        Schema::create('stock_transfer_documents', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('transfer_id')->constrained('transfers')->cascadeOnDelete();
            $table->string('document_type', 50)->index(); // packing_list, delivery_note, photo, etc.
            $table->string('file_name', 255);
            $table->string('file_path', 500);
            $table->string('file_type', 50)->nullable();
            $table->integer('file_size')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['transfer_id', 'document_type']);
        });

        // Stock Transfer History
        Schema::create('stock_transfer_history', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('transfer_id')->constrained('transfers')->cascadeOnDelete();
            $table->string('from_status', 50);
            $table->string('to_status', 50);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->nullable();
            $table->timestamps();
            $table->index(['transfer_id', 'changed_at']);
            $table->index(['to_status', 'changed_at']);
        });

        // ===== LEAVE MANAGEMENT SYSTEM =====
        
        // Leave Types
        Schema::create('leave_types', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('code', 20)->unique();
            $table->text('description')->nullable();
            $table->enum('unit', ['days', 'hours'])->default('days');
            $table->decimal('default_annual_quota', 10, 2)->default(0);
            $table->boolean('is_paid')->default(true);
            $table->boolean('requires_approval')->default(true);
            $table->boolean('requires_document')->default(false);
            $table->integer('max_consecutive_days')->nullable();
            $table->integer('min_notice_days')->default(0);
            $table->integer('max_carry_forward')->nullable();
            $table->boolean('carry_forward_expires')->default(false);
            $table->integer('carry_forward_expiry_months')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0);
            $table->string('color', 7)->default('#3B82F6');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['is_active', 'sort_order']);
        });

        // Leave Balances
        Schema::create('leave_balances', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->year('year')->index();
            $table->decimal('opening_balance', 10, 2)->default(0);
            $table->decimal('annual_quota', 10, 2)->default(0);
            $table->decimal('accrued', 10, 2)->default(0);
            $table->decimal('used', 10, 2)->default(0);
            $table->decimal('pending', 10, 2)->default(0);
            $table->decimal('available', 10, 2)->default(0);
            $table->decimal('carry_forward_from_previous', 10, 2)->default(0);
            $table->date('carry_forward_expiry_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'leave_type_id', 'year'], 'unique_employee_leave_year');
            $table->index(['employee_id', 'year']);
        });

        // Leave Request Approvals
        Schema::create('leave_request_approvals', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('leave_request_id')->constrained()->cascadeOnDelete();
            $table->integer('approval_level')->default(1);
            $table->foreignId('approver_id')->constrained('users')->restrictOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            $table->text('comments')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->index(['leave_request_id', 'approval_level']);
            $table->index(['approver_id', 'status']);
        });

        // Leave Adjustments
        Schema::create('leave_adjustments', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->restrictOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->year('year');
            $table->enum('adjustment_type', ['addition', 'deduction', 'correction', 'carry_forward', 'encashment'])->index();
            $table->decimal('amount', 10, 2);
            $table->text('reason');
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'year']);
        });

        // Leave Holidays
        Schema::create('leave_holidays', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->string('name', 200);
            $table->date('date')->index();
            $table->year('year')->index();
            $table->enum('type', ['public', 'company', 'regional', 'religious'])->default('public')->index();
            $table->boolean('is_mandatory')->default(true);
            $table->text('description')->nullable();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['date', 'is_active']);
            $table->index(['year', 'type', 'is_active']);
        });

        // Leave Accrual Rules
        Schema::create('leave_accrual_rules', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->enum('accrual_frequency', ['monthly', 'quarterly', 'semi_annually', 'annually', 'per_pay_period'])->default('monthly');
            $table->decimal('accrual_amount', 10, 2);
            $table->boolean('prorate_on_joining')->default(true);
            $table->boolean('prorate_on_leaving')->default(true);
            $table->integer('waiting_period_months')->default(0);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['leave_type_id', 'is_active']);
        });

        // Leave Encashments
        Schema::create('leave_encashments', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->string('encashment_number', 50)->unique();
            $table->foreignId('employee_id')->constrained('hr_employees')->restrictOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->year('year');
            $table->decimal('days_encashed', 10, 2);
            $table->decimal('rate_per_day', 15, 2);
            $table->decimal('total_amount', 15, 2);
            $table->string('currency', 3)->default('EGP');
            $table->enum('status', ['pending', 'approved', 'processed', 'paid', 'rejected'])->default('pending')->index();
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'year', 'status']);
        });
    }

    public function down(): void
    {
        // Drop in reverse order of creation
        Schema::dropIfExists('leave_encashments');
        Schema::dropIfExists('leave_accrual_rules');
        Schema::dropIfExists('leave_holidays');
        Schema::dropIfExists('leave_adjustments');
        Schema::dropIfExists('leave_request_approvals');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('stock_transfer_history');
        Schema::dropIfExists('stock_transfer_documents');
        Schema::dropIfExists('stock_transfer_approvals');
        Schema::dropIfExists('supplier_performance_metrics');
        Schema::dropIfExists('debit_notes');
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
        Schema::dropIfExists('return_refunds');
        Schema::dropIfExists('credit_note_applications');
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('sales_return_items');
        Schema::dropIfExists('sales_returns');
    }
};
