<?php

declare(strict_types=1);

/**
 * Add track_stock_alerts column to products table
 * 
 * This column is used to enable/disable low stock alerts
 * for individual products.
 */

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
        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'track_stock_alerts')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('track_stock_alerts')->default(true)->after('is_batch_tracked');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'track_stock_alerts')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('track_stock_alerts');
            });
        }
    }
};
