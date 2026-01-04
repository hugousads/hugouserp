<?php

declare(strict_types=1);

/**
 * Consolidated Sales & Purchases Tables Migration
 * 
 * MySQL 8.4 Optimized:
 * - Sales orders, invoices, payments
 * - Purchase orders, GRN
 * - Returns and refunds
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
        // Sales/Invoices
        Schema::create('sales', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('customer_id')->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('reference_number', 100)->unique();
            $table->string('type', 50)->default('sale'); // sale, quotation, order
            $table->string('status', 50)->default('pending'); // pending, confirmed, processing, completed, cancelled
            $table->string('payment_status', 50)->default('unpaid'); // unpaid, partial, paid
            
            // Dates
            $table->date('sale_date');
            $table->date('due_date')->nullable();
            $table->date('delivery_date')->nullable();
            
            // Amounts
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('discount_type', 5, 2)->nullable(); // percent or amount
            $table->decimal('discount_amount', 18, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('shipping_amount', 18, 4)->default(0);
            $table->decimal('total_amount', 18, 4)->default(0);
            $table->decimal('paid_amount', 18, 4)->default(0);
            $table->decimal('change_amount', 18, 4)->default(0);
            $table->string('currency', 3)->default('EGP');
            $table->decimal('exchange_rate', 18, 8)->default(1);
            
            // Shipping
            $table->text('shipping_address')->nullable();
            $table->string('shipping_method', 100)->nullable();
            $table->string('tracking_number', 100)->nullable();
            
            // Additional
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('terms_conditions')->nullable();
            $table->json('custom_fields')->nullable();
            
            // References
            $table->foreignId('store_order_id')->nullable();
            $table->foreignId('quotation_id')->nullable();
            $table->foreignId('salesperson_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            // POS specific
            $table->foreignId('pos_session_id')->nullable();
            $table->boolean('is_pos_sale')->default(false);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['branch_id', 'sale_date']);
            $table->index(['customer_id', 'status']);
            $table->index(['status', 'payment_status']);
            $table->index('sale_date');
        });

        // Sale items
        Schema::create('sale_items', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('variation_id')->nullable()
                ->constrained('product_variations')
                ->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained();
            
            $table->string('product_name', 255);
            $table->string('sku', 100)->nullable();
            $table->decimal('quantity', 18, 4);
            $table->foreignId('unit_id')->nullable()
                ->constrained('units_of_measure')
                ->nullOnDelete();
            $table->decimal('unit_price', 18, 4);
            $table->decimal('cost_price', 18, 4)->nullable();
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('line_total', 18, 4);
            
            // Batch/Serial tracking
            $table->foreignId('batch_id')->nullable()
                ->constrained('inventory_batches')
                ->nullOnDelete();
            $table->json('serial_numbers')->nullable();
            
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['sale_id', 'product_id']);
        });

        // Sale payments
        Schema::create('sale_payments', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->string('reference_number', 100)->nullable();
            $table->decimal('amount', 18, 4);
            $table->string('payment_method', 50); // cash, card, bank_transfer, cheque
            $table->string('status', 50)->default('completed'); // pending, completed, failed, refunded
            $table->date('payment_date');
            $table->string('currency', 3)->default('EGP');
            $table->decimal('exchange_rate', 18, 8)->default(1);
            
            // Card/Bank details
            $table->string('card_last_four', 4)->nullable();
            $table->string('bank_name', 255)->nullable();
            $table->string('cheque_number', 100)->nullable();
            $table->date('cheque_date')->nullable();
            
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            
            $table->index(['sale_id', 'status']);
        });

        // Purchases
        Schema::create('purchases', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('supplier_id')->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('reference_number', 100)->unique();
            $table->string('supplier_invoice', 100)->nullable();
            $table->string('type', 50)->default('purchase'); // purchase, order, quotation
            $table->string('status', 50)->default('pending'); // pending, confirmed, received, completed, cancelled
            $table->string('payment_status', 50)->default('unpaid');
            
            // Dates
            $table->date('purchase_date');
            $table->date('due_date')->nullable();
            $table->date('expected_date')->nullable();
            
            // Amounts
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('shipping_amount', 18, 4)->default(0);
            $table->decimal('other_charges', 18, 4)->default(0);
            $table->decimal('total_amount', 18, 4)->default(0);
            $table->decimal('paid_amount', 18, 4)->default(0);
            $table->string('currency', 3)->default('EGP');
            $table->decimal('exchange_rate', 18, 8)->default(1);
            
            $table->text('notes')->nullable();
            $table->text('terms_conditions')->nullable();
            $table->json('custom_fields')->nullable();
            
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('approved_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['branch_id', 'purchase_date']);
            $table->index(['supplier_id', 'status']);
            $table->index('purchase_date');
        });

        // Purchase items
        Schema::create('purchase_items', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('variation_id')->nullable()
                ->constrained('product_variations')
                ->nullOnDelete();
            
            $table->string('product_name', 255);
            $table->string('sku', 100)->nullable();
            $table->decimal('quantity', 18, 4);
            $table->decimal('received_quantity', 18, 4)->default(0);
            $table->foreignId('unit_id')->nullable()
                ->constrained('units_of_measure')
                ->nullOnDelete();
            $table->decimal('unit_price', 18, 4);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('line_total', 18, 4);
            
            $table->date('expiry_date')->nullable();
            $table->string('batch_number', 100)->nullable();
            
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['purchase_id', 'product_id']);
        });

        // Purchase requisitions
        Schema::create('purchase_requisitions', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained();
            $table->string('reference_number', 100)->unique();
            $table->string('status', 50)->default('draft'); // draft, pending, approved, rejected, converted
            $table->string('priority', 50)->default('normal'); // low, normal, high, urgent
            $table->date('required_date')->nullable();
            $table->text('purpose')->nullable();
            $table->text('notes')->nullable();
            
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['branch_id', 'status']);
        });

        // Purchase requisition items
        Schema::create('purchase_requisition_items', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('requisition_id')
                ->constrained('purchase_requisitions')
                ->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->decimal('quantity', 18, 4);
            $table->foreignId('unit_id')->nullable()
                ->constrained('units_of_measure')
                ->nullOnDelete();
            $table->decimal('estimated_price', 18, 4)->nullable();
            $table->text('specifications')->nullable();
            $table->foreignId('preferred_supplier_id')->nullable()
                ->constrained('suppliers')
                ->nullOnDelete();
            $table->timestamps();
        });

        // Supplier quotations
        Schema::create('supplier_quotations', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('requisition_id')->nullable()
                ->constrained('purchase_requisitions')
                ->nullOnDelete();
            $table->string('reference_number', 100)->unique();
            $table->string('supplier_reference', 100)->nullable();
            $table->string('status', 50)->default('pending'); // pending, received, accepted, rejected, expired
            $table->date('quotation_date');
            $table->date('valid_until')->nullable();
            
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('total_amount', 18, 4)->default(0);
            $table->string('currency', 3)->default('EGP');
            $table->integer('delivery_days')->nullable();
            $table->text('terms_conditions')->nullable();
            $table->text('notes')->nullable();
            
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['supplier_id', 'status']);
        });

        // Supplier quotation items
        Schema::create('supplier_quotation_items', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('quotation_id')
                ->constrained('supplier_quotations')
                ->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('line_total', 18, 4);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Goods received notes
        Schema::create('goods_received_notes', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('purchase_id')->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('reference_number', 100)->unique();
            $table->string('supplier_delivery_note', 100)->nullable();
            $table->string('status', 50)->default('pending'); // pending, inspecting, completed, rejected
            $table->date('received_date');
            
            $table->text('notes')->nullable();
            $table->string('received_by_name', 255)->nullable();
            
            $table->foreignId('received_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('inspected_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('inspected_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['branch_id', 'received_date']);
        });

        // GRN items
        Schema::create('grn_items', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('grn_id')
                ->constrained('goods_received_notes')
                ->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('purchase_item_id')->nullable();
            $table->decimal('expected_quantity', 18, 4);
            $table->decimal('received_quantity', 18, 4);
            $table->decimal('accepted_quantity', 18, 4)->nullable();
            $table->decimal('rejected_quantity', 18, 4)->default(0);
            $table->string('rejection_reason', 255)->nullable();
            $table->string('batch_number', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('quality_status', 50)->nullable(); // passed, failed, pending
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Return notes
        Schema::create('return_notes', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('reference_number', 100)->unique();
            $table->string('type', 50); // sale_return, purchase_return
            $table->foreignId('sale_id')->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('purchase_id')->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('customer_id')->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained();
            $table->string('status', 50)->default('pending');
            $table->date('return_date');
            
            $table->text('reason')->nullable();
            $table->decimal('total_amount', 18, 4)->default(0);
            $table->string('refund_method', 50)->nullable();
            $table->boolean('restock_items')->default(true);
            
            $table->foreignId('processed_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['branch_id', 'type']);
        });

        // Receipts
        Schema::create('receipts', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('sale_id')->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('payment_id')->nullable()
                ->constrained('sale_payments')
                ->nullOnDelete();
            $table->string('receipt_number', 100)->unique();
            $table->decimal('amount', 18, 4);
            $table->string('type', 50)->default('payment'); // payment, refund
            $table->timestamp('printed_at')->nullable();
            $table->json('print_data')->nullable();
            $table->timestamps();
        });

        // Deliveries
        Schema::create('deliveries', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('sale_id')->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('reference_number', 100)->unique();
            $table->string('status', 50)->default('pending'); // pending, dispatched, delivered, failed
            $table->date('scheduled_date')->nullable();
            $table->date('delivery_date')->nullable();
            $table->text('delivery_address')->nullable();
            $table->string('recipient_name', 255)->nullable();
            $table->string('recipient_phone', 50)->nullable();
            $table->string('driver_name', 255)->nullable();
            $table->string('vehicle_number', 50)->nullable();
            $table->decimal('shipping_cost', 18, 4)->default(0);
            $table->text('notes')->nullable();
            $table->string('signature_image', 500)->nullable();
            
            $table->foreignId('delivered_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('return_notes');
        Schema::dropIfExists('grn_items');
        Schema::dropIfExists('goods_received_notes');
        Schema::dropIfExists('supplier_quotation_items');
        Schema::dropIfExists('supplier_quotations');
        Schema::dropIfExists('purchase_requisition_items');
        Schema::dropIfExists('purchase_requisitions');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
