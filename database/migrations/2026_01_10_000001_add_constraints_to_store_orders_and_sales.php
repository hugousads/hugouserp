<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add foreign key constraint for store_orders.branch_id → branches.id
        Schema::table('store_orders', function (Blueprint $table) {
            // Skip if constraint already exists by catching exception
            try {
                $table->foreign('branch_id')
                    ->references('id')
                    ->on('branches')
                    ->nullOnDelete();
            } catch (\Illuminate\Database\QueryException $e) {
                // Foreign key might already exist - only catch query exceptions
            }
        });

        // Add store_order_id column to sales if it doesn't exist, then add foreign key
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'store_order_id')) {
                $table->unsignedBigInteger('store_order_id')->nullable()->after('warehouse_id');
            }
        });

        Schema::table('sales', function (Blueprint $table) {
            // Skip if constraint already exists by catching exception
            try {
                $table->foreign('store_order_id')
                    ->references('id')
                    ->on('store_orders')
                    ->nullOnDelete();
            } catch (\Illuminate\Database\QueryException $e) {
                // Foreign key might already exist - only catch query exceptions
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['store_order_id']);
            $table->dropColumn('store_order_id');
        });

        Schema::table('store_orders', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
        });
    }
};
