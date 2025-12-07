<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->comment('Product ID');
            $table->unsignedBigInteger('warehouse_id')->comment('Warehouse ID');
            $table->unsignedBigInteger('branch_id')->comment('Branch ID');
            $table->string('batch_number')->comment('Batch number');
            $table->date('manufacturing_date')->nullable()->comment('Manufacturing date');
            $table->date('expiry_date')->nullable()->comment('Expiry date');
            $table->decimal('quantity', 18, 4)->default(0)->comment('Available quantity');
            $table->decimal('unit_cost', 18, 4)->default(0)->comment('Unit cost for this batch');
            $table->string('supplier_batch_ref')->nullable()->comment('Supplier batch reference');
            $table->unsignedBigInteger('purchase_id')->nullable()->comment('Purchase ID this batch came from');
            $table->string('status')->default('active')->comment('Status: active, expired, depleted');
            $table->text('notes')->nullable()->comment('Additional notes');
            $table->json('meta')->nullable()->comment('Additional metadata');
            $table->timestamps();
            
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->foreign('purchase_id')->references('id')->on('purchases')->onDelete('set null');
            
            $table->index(['product_id', 'warehouse_id']);
            $table->index(['branch_id', 'status']);
            $table->index('expiry_date');
            // Batch number is unique per product-warehouse combination
            // This ensures same batch can exist in different warehouses for same product
            $table->unique(['product_id', 'warehouse_id', 'batch_number'], 'batch_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_batches');
    }
};
