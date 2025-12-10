<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add category_id and unit_id columns to products table
 * 
 * These columns are used by the Product model but were missing from the original migration.
 * This ensures the schema matches the model's expectations.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Add category_id if it doesn't exist
            if (!Schema::hasColumn('products', 'category_id')) {
                $table->unsignedBigInteger('category_id')->nullable()->after('module_id');
                
                // Add foreign key constraint to product_categories
                if (Schema::hasTable('product_categories')) {
                    $table->foreign('category_id')
                        ->references('id')
                        ->on('product_categories')
                        ->onDelete('set null');
                }
                
                // Add index for performance
                $table->index('category_id');
            }
            
            // Add unit_id if it doesn't exist
            if (!Schema::hasColumn('products', 'unit_id')) {
                $table->unsignedBigInteger('unit_id')->nullable()->after('uom_factor');
                
                // Add foreign key constraint to units_of_measure
                if (Schema::hasTable('units_of_measure')) {
                    $table->foreign('unit_id')
                        ->references('id')
                        ->on('units_of_measure')
                        ->onDelete('set null');
                }
                
                // Add index for performance
                $table->index('unit_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Drop foreign keys first
            if (Schema::hasColumn('products', 'category_id')) {
                $table->dropForeign(['category_id']);
                $table->dropIndex(['category_id']);
                $table->dropColumn('category_id');
            }
            
            if (Schema::hasColumn('products', 'unit_id')) {
                $table->dropForeign(['unit_id']);
                $table->dropIndex(['unit_id']);
                $table->dropColumn('unit_id');
            }
        });
    }
};
