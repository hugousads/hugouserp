<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_serials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->comment('Product ID');
            $table->unsignedBigInteger('warehouse_id')->nullable()->comment('Current warehouse ID');
            $table->unsignedBigInteger('branch_id')->comment('Branch ID');
            $table->string('serial_number')->unique()->comment('Unique serial number');
            $table->unsignedBigInteger('batch_id')->nullable()->comment('Associated batch ID');
            $table->decimal('unit_cost', 18, 4)->default(0)->comment('Unit cost for this serial');
            $table->date('warranty_start')->nullable()->comment('Warranty start date');
            $table->date('warranty_end')->nullable()->comment('Warranty end date');
            $table->string('status')->default('in_stock')->comment('Status: in_stock, sold, returned, defective');
            $table->unsignedBigInteger('customer_id')->nullable()->comment('Current owner customer ID');
            $table->unsignedBigInteger('sale_id')->nullable()->comment('Sale ID if sold');
            $table->unsignedBigInteger('purchase_id')->nullable()->comment('Purchase ID this came from');
            $table->text('notes')->nullable()->comment('Additional notes');
            $table->json('meta')->nullable()->comment('Additional metadata');
            $table->timestamps();
            
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->foreign('batch_id')->references('id')->on('inventory_batches')->onDelete('set null');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
            $table->foreign('sale_id')->references('id')->on('sales')->onDelete('set null');
            $table->foreign('purchase_id')->references('id')->on('purchases')->onDelete('set null');
            
            $table->index(['product_id', 'status']);
            $table->index(['branch_id', 'warehouse_id']);
            $table->index('warranty_end');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_serials');
    }
};
