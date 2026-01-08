<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Sales Returns & Credit Notes System.
     * Critical feature for handling refunds, returns, and accounting adjustments.
     */
    public function up(): void
    {
        // Sales Returns Table - Track returned items with reason and condition
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number', 50)->unique();
            $table->foreignId('sale_id')->constrained('sales')->onDelete('restrict');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('restrict');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->onDelete('restrict');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('restrict');
            $table->enum('return_type', ['full', 'partial'])->default('partial');
            $table->enum('status', ['pending', 'approved', 'rejected', 'completed', 'cancelled'])->default('pending')->index();
            $table->string('reason', 255)->nullable(); // defective, wrong_item, damaged, customer_change_mind, etc.
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0)->index();
            $table->decimal('refund_amount', 15, 2)->default(0); // Actual refunded amount (may differ)
            $table->string('currency', 3)->default('EGP');
            $table->enum('refund_method', ['original', 'cash', 'bank_transfer', 'credit', 'store_credit'])->default('original');
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable(); // For staff only
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // Performance indexes
            $table->index(['branch_id', 'status', 'created_at']);
            $table->index(['customer_id', 'created_at']);
            $table->index('return_number');
        });

        // Sales Return Items - Individual items being returned
        Schema::create('sales_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_return_id')->constrained('sales_returns')->onDelete('cascade');
            $table->foreignId('sale_item_id')->constrained('sale_items')->onDelete('restrict');
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('restrict');
            $table->decimal('qty_returned', 15, 3)->default(0); // Quantity being returned
            $table->decimal('qty_original', 15, 3)->default(0); // Original quantity sold
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->enum('condition', ['new', 'used', 'damaged', 'defective'])->default('new');
            $table->string('reason', 255)->nullable(); // Item-specific reason
            $table->text('notes')->nullable();
            $table->boolean('restock')->default(true); // Should item return to inventory?
            $table->foreignId('restocked_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('restocked_at')->nullable();
            $table->timestamps();

            // Performance indexes
            $table->index(['sales_return_id', 'product_id']);
            $table->index('sale_item_id');
        });

        // Credit Notes - Accounting documents for returns and adjustments
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->string('credit_note_number', 50)->unique();
            $table->foreignId('sales_return_id')->nullable()->constrained('sales_returns')->onDelete('restrict');
            $table->foreignId('sale_id')->constrained('sales')->onDelete('restrict');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('restrict');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('restrict');
            $table->enum('type', ['return', 'adjustment', 'discount', 'refund', 'other'])->default('return');
            $table->enum('status', ['draft', 'pending', 'approved', 'applied', 'cancelled'])->default('draft')->index();
            $table->decimal('amount', 15, 2)->default(0)->index();
            $table->string('currency', 3)->default('EGP');
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->date('issue_date')->default(DB::raw('CURRENT_DATE'))->index();
            $table->date('applied_date')->nullable(); // When credit was applied to customer account
            $table->boolean('auto_apply')->default(true); // Auto-apply to customer balance
            $table->decimal('applied_amount', 15, 2)->default(0); // Amount actually applied
            $table->decimal('remaining_amount', 15, 2)->default(0); // Unused credit balance
            
            // Accounting integration
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->onDelete('set null');
            $table->boolean('posted_to_accounting')->default(false)->index();
            $table->timestamp('posted_at')->nullable();
            
            // Approval workflow
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // Performance indexes
            $table->index(['branch_id', 'status', 'issue_date']);
            $table->index(['customer_id', 'status']);
            $table->index(['posted_to_accounting', 'status']);
        });

        // Credit Note Applications - Track how credit notes are applied/used
        Schema::create('credit_note_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_id')->constrained('credit_notes')->onDelete('cascade');
            $table->foreignId('sale_id')->nullable()->constrained('sales')->onDelete('restrict'); // Applied to which sale
            $table->decimal('applied_amount', 15, 2)->default(0);
            $table->date('application_date')->default(DB::raw('CURRENT_DATE'));
            $table->text('notes')->nullable();
            $table->foreignId('applied_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            // Performance indexes
            $table->index(['credit_note_id', 'application_date']);
            $table->index('sale_id');
        });

        // Return Refunds - Track actual refund transactions
        Schema::create('return_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_return_id')->constrained('sales_returns')->onDelete('restrict');
            $table->foreignId('credit_note_id')->nullable()->constrained('credit_notes')->onDelete('set null');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('restrict');
            $table->enum('refund_method', ['cash', 'bank_transfer', 'credit_card', 'store_credit', 'original_method'])->index();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('currency', 3)->default('EGP');
            $table->string('reference_number', 100)->nullable()->index();
            $table->string('transaction_id', 100)->nullable(); // Payment gateway transaction ID
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending')->index();
            $table->text('notes')->nullable();
            
            // Bank/Card details for refund tracking
            $table->string('bank_name', 100)->nullable();
            $table->string('account_number', 50)->nullable();
            $table->string('card_last_four', 4)->nullable();
            
            $table->foreignId('processed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            // Performance indexes
            $table->index(['sales_return_id', 'status']);
            $table->index(['refund_method', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_refunds');
        Schema::dropIfExists('credit_note_applications');
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('sales_return_items');
        Schema::dropIfExists('sales_returns');
    }
};
