<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add advanced inventory management fields to products table.
     * 
     * ENHANCEMENTS:
     * - Add stock quantity tracking (currently missing)
     * - Add stock alert threshold
     * - Add warranty tracking
     * - Add dimensions and weight for shipping
     * - Add manufacturer and brand info
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Stock tracking - CRITICAL MISSING FIELDS
            if (!Schema::hasColumn('products', 'stock_quantity')) {
                $table->decimal('stock_quantity', 18, 4)->default(0)->after('reorder_qty')->comment('Current stock quantity');
            }
            if (!Schema::hasColumn('products', 'stock_alert_threshold')) {
                $table->decimal('stock_alert_threshold', 18, 4)->nullable()->after('stock_quantity')->comment('Low stock alert threshold');
            }
            $table->decimal('reserved_quantity', 18, 4)->default(0)->after('stock_alert_threshold')->comment('Quantity reserved in pending orders');
            $table->decimal('available_quantity', 18, 4)->storedAs('stock_quantity - reserved_quantity')->after('reserved_quantity')->comment('Available quantity for sale');
            
            // Warranty tracking
            $table->boolean('has_warranty')->default(false)->after('track_stock_alerts')->comment('Product has warranty');
            $table->integer('warranty_period_days')->default(0)->after('has_warranty')->comment('Warranty period in days');
            $table->string('warranty_type')->nullable()->after('warranty_period_days')->comment('Warranty type: manufacturer, extended, etc.');
            
            // Physical dimensions for shipping
            $table->decimal('length', 10, 2)->nullable()->after('warranty_type')->comment('Length in cm');
            $table->decimal('width', 10, 2)->nullable()->after('length')->comment('Width in cm');
            $table->decimal('height', 10, 2)->nullable()->after('width')->comment('Height in cm');
            $table->decimal('weight', 10, 2)->nullable()->after('height')->comment('Weight in kg');
            $table->decimal('volumetric_weight', 10, 2)->storedAs('(length * width * height) / 5000')->after('weight')->comment('Volumetric weight for shipping');
            
            // Manufacturer and brand
            $table->string('manufacturer')->nullable()->after('volumetric_weight')->comment('Manufacturer name');
            $table->string('brand')->nullable()->after('manufacturer')->comment('Brand name');
            $table->string('model_number')->nullable()->after('brand')->comment('Model number');
            $table->string('origin_country')->nullable()->after('model_number')->comment('Country of origin');
            
            // Product lifecycle
            $table->date('manufacture_date')->nullable()->after('origin_country')->comment('Manufacture date');
            $table->date('expiry_date')->nullable()->after('manufacture_date')->comment('Expiry date for perishable items');
            $table->boolean('is_perishable')->default(false)->after('expiry_date')->comment('Is product perishable');
            $table->integer('shelf_life_days')->nullable()->after('is_perishable')->comment('Shelf life in days');
            
            // Sales and purchase preferences
            $table->boolean('allow_backorder')->default(false)->after('shelf_life_days')->comment('Allow orders when out of stock');
            $table->boolean('requires_approval')->default(false)->after('allow_backorder')->comment('Requires manager approval for sales');
            $table->decimal('minimum_order_quantity', 18, 4)->default(1)->after('requires_approval')->comment('Minimum quantity for orders');
            $table->decimal('maximum_order_quantity', 18, 4)->nullable()->after('minimum_order_quantity')->comment('Maximum quantity for orders');
            
            // Pricing
            $table->decimal('msrp', 18, 4)->nullable()->after('maximum_order_quantity')->comment('Manufacturer suggested retail price');
            $table->decimal('wholesale_price', 18, 4)->nullable()->after('msrp')->comment('Wholesale price');
            $table->date('last_cost_update')->nullable()->after('wholesale_price')->comment('Last cost update date');
            $table->date('last_price_update')->nullable()->after('last_cost_update')->comment('Last price update date');
            
            // Indexes for performance
            $table->index('stock_quantity');
            $table->index(['branch_id', 'stock_quantity'], 'prod_branch_stock_idx');
            $table->index('expiry_date');
            $table->index('manufacturer');
            $table->index('brand');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('prod_branch_stock_idx');
            $table->dropIndex(['stock_quantity']);
            $table->dropIndex(['expiry_date']);
            $table->dropIndex(['manufacturer']);
            $table->dropIndex(['brand']);
            $table->dropColumn([
                'reserved_quantity', 'available_quantity',
                'has_warranty', 'warranty_period_days', 'warranty_type',
                'length', 'width', 'height', 'weight', 'volumetric_weight',
                'manufacturer', 'brand', 'model_number', 'origin_country',
                'manufacture_date', 'expiry_date', 'is_perishable', 'shelf_life_days',
                'allow_backorder', 'requires_approval', 'minimum_order_quantity', 'maximum_order_quantity',
                'msrp', 'wholesale_price', 'last_cost_update', 'last_price_update'
            ]);
            // Note: stock_quantity and stock_alert_threshold are NOT dropped as they might exist
        });
    }
};
