<?php

declare(strict_types=1);

/**
 * Consolidated Accounting & Finance Tables Migration
 * 
 * MySQL 8.4 Optimized:
 * - Chart of accounts, journal entries
 * - Banking, reconciliation
 * - Fixed assets, depreciation
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
        // Fiscal periods
        Schema::create('fiscal_periods', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 255);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 50)->default('open'); // open, closed, locked
            $table->boolean('is_current')->default(false)->index();
            $table->timestamps();
            
            $table->index(['start_date', 'end_date']);
        });

        // Chart of accounts
        Schema::create('accounts', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->string('name_ar', 255)->nullable();
            $table->string('type', 50); // asset, liability, equity, revenue, expense
            $table->string('sub_type', 50)->nullable();
            $table->foreignId('parent_id')->nullable()
                ->constrained('accounts')
                ->nullOnDelete();
            $table->text('description')->nullable();
            $table->decimal('opening_balance', 18, 4)->default(0);
            $table->decimal('current_balance', 18, 4)->default(0);
            $table->string('currency', 3)->default('EGP');
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_system')->default(false);
            $table->boolean('allow_manual_entries')->default(true);
            $table->integer('level')->default(1);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['type', 'is_active']);
            $table->index(['parent_id', 'is_active']);
        });

        // Account mappings for automation
        Schema::create('account_mappings', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mapping_key', 100); // sales_revenue, purchase_expense, inventory, etc.
            $table->foreignId('account_id')->constrained();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['branch_id', 'mapping_key']);
        });

        // Journal entries
        Schema::create('journal_entries', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiscal_period_id')->nullable()
                ->constrained('fiscal_periods')
                ->nullOnDelete();
            $table->string('reference_number', 100)->unique();
            $table->string('type', 50)->default('manual'); // manual, auto, adjustment, closing
            $table->date('entry_date')->index();
            $table->text('description')->nullable();
            $table->string('reference_type', 255)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('status', 50)->default('draft'); // draft, posted, reversed
            $table->decimal('total_debit', 18, 4)->default(0);
            $table->decimal('total_credit', 18, 4)->default(0);
            $table->string('currency', 3)->default('EGP');
            $table->decimal('exchange_rate', 18, 8)->default(1);
            
            $table->foreignId('reversed_entry_id')->nullable()
                ->constrained('journal_entries')
                ->nullOnDelete();
            
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('approved_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['branch_id', 'entry_date']);
            $table->index(['reference_type', 'reference_id']);
        });

        // Journal entry lines
        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('journal_entry_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('account_id')->constrained();
            $table->decimal('debit', 18, 4)->default(0);
            $table->decimal('credit', 18, 4)->default(0);
            $table->text('description')->nullable();
            $table->string('reference', 255)->nullable();
            $table->timestamps();
            
            $table->index(['account_id', 'created_at']);
        });

        // Bank accounts
        Schema::create('bank_accounts', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()
                ->constrained('accounts')
                ->nullOnDelete();
            $table->string('bank_name', 255);
            $table->string('account_name', 255);
            $table->string('account_number', 100);
            $table->string('iban', 50)->nullable();
            $table->string('swift_code', 20)->nullable();
            $table->string('branch_name', 255)->nullable();
            $table->string('branch_address', 500)->nullable();
            $table->string('currency', 3)->default('EGP');
            $table->decimal('opening_balance', 18, 4)->default(0);
            $table->decimal('current_balance', 18, 4)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        // Bank transactions
        Schema::create('bank_transactions', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->string('reference_number', 100);
            $table->string('type', 50); // deposit, withdrawal, transfer, fee, interest
            $table->date('transaction_date')->index();
            $table->decimal('amount', 18, 4);
            $table->decimal('balance_after', 18, 4);
            $table->text('description')->nullable();
            $table->string('payee', 255)->nullable();
            $table->string('cheque_number', 100)->nullable();
            $table->boolean('is_reconciled')->default(false)->index();
            $table->foreignId('reconciliation_id')->nullable();
            
            // Link to journal
            $table->foreignId('journal_entry_id')->nullable()
                ->constrained()
                ->nullOnDelete();
            
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->timestamps();
            
            $table->index(['bank_account_id', 'transaction_date']);
        });

        // Bank reconciliations
        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->string('reference_number', 100)->unique();
            $table->date('statement_date');
            $table->decimal('statement_balance', 18, 4);
            $table->decimal('book_balance', 18, 4);
            $table->decimal('adjusted_balance', 18, 4);
            $table->decimal('difference', 18, 4)->default(0);
            $table->string('status', 50)->default('draft'); // draft, completed
            $table->text('notes')->nullable();
            
            $table->foreignId('reconciled_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable();
            
            $table->timestamps();
        });

        // Expense categories
        Schema::create('expense_categories', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 255);
            $table->string('name_ar', 255)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('account_id')->nullable()
                ->constrained('accounts')
                ->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['branch_id', 'name']);
        });

        // Expenses
        Schema::create('expenses', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')
                ->constrained('expense_categories');
            $table->string('reference_number', 100)->unique();
            $table->date('expense_date')->index();
            $table->decimal('amount', 18, 4);
            $table->foreignId('tax_id')->nullable()
                ->constrained('taxes')
                ->nullOnDelete();
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('total_amount', 18, 4);
            $table->string('payment_method', 50)->nullable();
            $table->foreignId('bank_account_id')->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('status', 50)->default('pending'); // pending, approved, paid
            $table->text('description')->nullable();
            $table->string('vendor_name', 255)->nullable();
            $table->string('receipt_number', 100)->nullable();
            $table->json('attachments')->nullable();
            
            $table->foreignId('journal_entry_id')->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('approved_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['branch_id', 'expense_date']);
        });

        // Income categories
        Schema::create('income_categories', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 255);
            $table->string('name_ar', 255)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('account_id')->nullable()
                ->constrained('accounts')
                ->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['branch_id', 'name']);
        });

        // Incomes
        Schema::create('incomes', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')
                ->constrained('income_categories');
            $table->string('reference_number', 100)->unique();
            $table->date('income_date')->index();
            $table->decimal('amount', 18, 4);
            $table->foreignId('tax_id')->nullable()
                ->constrained('taxes')
                ->nullOnDelete();
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('total_amount', 18, 4);
            $table->string('payment_method', 50)->nullable();
            $table->foreignId('bank_account_id')->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('status', 50)->default('received');
            $table->text('description')->nullable();
            $table->string('payer_name', 255)->nullable();
            $table->string('receipt_number', 100)->nullable();
            $table->json('attachments')->nullable();
            
            $table->foreignId('journal_entry_id')->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['branch_id', 'income_date']);
        });

        // Fixed assets
        Schema::create('fixed_assets', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('asset_code', 50)->unique();
            $table->string('name', 255);
            $table->string('name_ar', 255)->nullable();
            $table->string('category', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->string('location', 255)->nullable();
            $table->foreignId('assigned_to')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            // Financial
            $table->date('purchase_date');
            $table->decimal('purchase_cost', 18, 4);
            $table->decimal('salvage_value', 18, 4)->default(0);
            $table->integer('useful_life_months');
            $table->string('depreciation_method', 50)->default('straight_line');
            $table->decimal('accumulated_depreciation', 18, 4)->default(0);
            $table->decimal('current_value', 18, 4);
            
            // Accounts
            $table->foreignId('asset_account_id')->nullable()
                ->constrained('accounts')
                ->nullOnDelete();
            $table->foreignId('depreciation_account_id')->nullable()
                ->constrained('accounts')
                ->nullOnDelete();
            $table->foreignId('expense_account_id')->nullable()
                ->constrained('accounts')
                ->nullOnDelete();
            
            // Status
            $table->string('status', 50)->default('active'); // active, disposed, fully_depreciated
            $table->date('disposal_date')->nullable();
            $table->decimal('disposal_value', 18, 4)->nullable();
            $table->text('disposal_notes')->nullable();
            
            // Maintenance
            $table->date('last_maintenance_date')->nullable();
            $table->date('next_maintenance_date')->nullable();
            $table->text('maintenance_notes')->nullable();
            
            // Warranty
            $table->date('warranty_expiry')->nullable();
            $table->string('warranty_vendor', 255)->nullable();
            
            $table->json('custom_fields')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['branch_id', 'status']);
        });

        // Asset depreciation records
        Schema::create('asset_depreciations', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->foreignId('fiscal_period_id')->nullable()
                ->constrained('fiscal_periods')
                ->nullOnDelete();
            $table->date('depreciation_date');
            $table->decimal('depreciation_amount', 18, 4);
            $table->decimal('accumulated_total', 18, 4);
            $table->decimal('remaining_value', 18, 4);
            $table->foreignId('journal_entry_id')->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->timestamps();
            
            $table->index(['asset_id', 'depreciation_date']);
        });

        // Asset maintenance logs
        Schema::create('asset_maintenance_logs', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->date('maintenance_date');
            $table->string('type', 50); // preventive, corrective, inspection
            $table->text('description');
            $table->decimal('cost', 18, 4)->default(0);
            $table->string('vendor', 255)->nullable();
            $table->date('next_scheduled')->nullable();
            $table->foreignId('performed_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });

        // Installment plans
        Schema::create('installment_plans', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('customer_id')->constrained();
            $table->string('reference_number', 100)->unique();
            $table->decimal('total_amount', 18, 4);
            $table->decimal('down_payment', 18, 4)->default(0);
            $table->decimal('financed_amount', 18, 4);
            $table->decimal('interest_rate', 5, 2)->default(0);
            $table->decimal('interest_amount', 18, 4)->default(0);
            $table->integer('number_of_installments');
            $table->decimal('installment_amount', 18, 4);
            $table->date('start_date');
            $table->string('frequency', 50)->default('monthly');
            $table->string('status', 50)->default('active'); // active, completed, defaulted
            $table->decimal('paid_amount', 18, 4)->default(0);
            $table->decimal('remaining_amount', 18, 4);
            $table->text('notes')->nullable();
            
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
        });

        // Installment payments
        Schema::create('installment_payments', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('plan_id')
                ->constrained('installment_plans')
                ->cascadeOnDelete();
            $table->integer('installment_number');
            $table->date('due_date');
            $table->decimal('amount', 18, 4);
            $table->decimal('principal', 18, 4)->default(0);
            $table->decimal('interest', 18, 4)->default(0);
            $table->decimal('penalty', 18, 4)->default(0);
            $table->decimal('paid_amount', 18, 4)->default(0);
            $table->date('paid_date')->nullable();
            $table->string('status', 50)->default('pending'); // pending, paid, overdue, partial
            $table->string('payment_method', 50)->nullable();
            $table->text('notes')->nullable();
            
            $table->foreignId('received_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->timestamps();
            
            $table->unique(['plan_id', 'installment_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_payments');
        Schema::dropIfExists('installment_plans');
        Schema::dropIfExists('asset_maintenance_logs');
        Schema::dropIfExists('asset_depreciations');
        Schema::dropIfExists('fixed_assets');
        Schema::dropIfExists('incomes');
        Schema::dropIfExists('income_categories');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('bank_reconciliations');
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('account_mappings');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('fiscal_periods');
    }
};
