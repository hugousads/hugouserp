<?php

declare(strict_types=1);

/**
 * Consolidated Inventory Tables Migration
 *
 * MySQL 8.4 Optimized:
 * - Products, categories, warehouses
 * - Stock management with batch/serial tracking
 * - Price groups and taxes
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
        // Product categories
        Schema::create('product_categories', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete(); // Branch field
            $table->string('name', 255);
            $table->string('name_ar', 255)->nullable();
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()
                ->constrained('product_categories')
                ->nullOnDelete();
            $table->string('image', 500)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); // Created by
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete(); // Updated by
            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'is_active']);
        });

        // Units of measure
        Schema::create('units_of_measure', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->string('name', 100);
            $table->string('name_ar', 100)->nullable();
            $table->string('symbol', 20);
            $table->string('type', 50)->default('unit'); // unit, weight, volume, length
            $table->foreignId('base_unit_id')->nullable()
                ->constrained('units_of_measure')
                ->nullOnDelete();
            $table->decimal('conversion_factor', 18, 8)->default(1);
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->boolean('is_base_unit')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); // Created by
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete(); // Updated by
            $table->timestamps();
            $table->softDeletes();
        });

        // Price groups
        Schema::create('price_groups', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->string('name', 255);
            $table->string('name_ar', 255)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        // Taxes
        Schema::create('taxes', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete(); // Branch field
            $table->string('code', 50)->nullable(); // Tax code
            $table->string('name', 255);
            $table->string('name_ar', 255)->nullable();
            $table->text('description')->nullable(); // Description
            $table->decimal('rate', 8, 4);
            $table->string('type', 50)->default('percentage'); // percentage, fixed
            $table->boolean('is_compound')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_inclusive')->default(false);
            $table->json('extra_attributes')->nullable(); // Extra attributes
            $table->timestamps();
            $table->softDeletes();
        });

        // Warehouses
        Schema::create('warehouses', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('name_ar', 255)->nullable();
            $table->string('code', 50)->unique();
            $table->string('type', 50)->default('warehouse'); // warehouse, store, transit
            $table->string('address', 500)->nullable();
            $table->string('phone', 50)->nullable();
            $table->foreignId('manager_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_default')->default(false);
            $table->boolean('allow_negative_stock')->default(false);
            $table->json('settings')->nullable();
            $table->json('extra_attributes')->nullable(); // Extra attributes
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'is_active']);
        });

        // Products
        Schema::create('products', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('module_id')->nullable()
                ->constrained('modules')
                ->nullOnDelete();
            $table->foreignId('category_id')->nullable()
                ->constrained('product_categories')
                ->nullOnDelete();
            $table->foreignId('parent_product_id')->nullable()
                ->constrained('products')
                ->nullOnDelete();
            $table->string('name', 255);
            $table->string('name_ar', 255)->nullable();
            $table->string('sku', 100)->unique();
            $table->string('barcode', 100)->nullable()->index();
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();

            // Pricing
            $table->decimal('cost_price', 18, 4)->default(0);
            $table->decimal('cost', 18, 4)->default(0);
            $table->decimal('standard_cost', 18, 4)->nullable();
            $table->decimal('selling_price', 18, 4)->default(0);
            $table->decimal('price', 18, 4)->nullable();
            $table->decimal('default_price', 18, 4)->nullable();
            $table->decimal('min_price', 18, 4)->nullable();
            $table->decimal('wholesale_price', 18, 4)->nullable();
            $table->decimal('msrp', 18, 4)->nullable();
            $table->decimal('max_discount_percent', 5, 2)->nullable();
            $table->string('cost_currency', 3)->nullable();
            $table->string('price_currency', 3)->nullable();
            $table->string('cost_method', 50)->nullable();
            $table->timestamp('last_cost_update')->nullable();
            $table->timestamp('last_price_update')->nullable();
            $table->foreignId('price_group_id')->nullable()
                ->constrained('price_groups')
                ->nullOnDelete();
            $table->foreignId('price_list_id')->nullable()
                ->constrained('price_groups')
                ->nullOnDelete();
            $table->foreignId('tax_id')->nullable()
                ->constrained('taxes')
                ->nullOnDelete();

            // Units
            $table->string('uom', 50)->nullable();
            $table->decimal('uom_factor', 10, 4)->nullable();
            $table->foreignId('unit_id')->nullable()
                ->constrained('units_of_measure')
                ->nullOnDelete();
            $table->foreignId('purchase_unit_id')->nullable()
                ->constrained('units_of_measure')
                ->nullOnDelete();
            $table->foreignId('sale_unit_id')->nullable()
                ->constrained('units_of_measure')
                ->nullOnDelete();

            // Stock
            $table->decimal('stock_quantity', 18, 4)->default(0);
            $table->decimal('reserved_quantity', 18, 4)->default(0);
            $table->decimal('min_stock', 18, 4)->nullable();
            $table->decimal('reorder_point', 18, 4)->nullable();
            $table->decimal('max_stock', 18, 4)->nullable();
            $table->decimal('reorder_qty', 18, 4)->nullable();
            $table->decimal('stock_alert_threshold', 18, 4)->nullable();
            $table->decimal('min_order_quantity', 18, 4)->nullable();
            $table->decimal('max_order_quantity', 18, 4)->nullable();
            $table->decimal('minimum_order_quantity', 18, 4)->nullable();
            $table->decimal('maximum_order_quantity', 18, 4)->nullable();
            $table->integer('lead_time_days')->nullable();
            $table->string('location_code', 100)->nullable();
            $table->string('stock_management', 50)->default('simple'); // simple, batch, serial

            // Type and status
            $table->string('type', 50)->default('product'); // product, service, spare_part, rental
            $table->string('product_type', 50)->nullable();
            $table->string('status', 50)->default('active');
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('has_variations')->default(false);
            $table->boolean('has_variants')->default(false);
            $table->json('variation_attributes')->nullable();
            $table->boolean('is_serialized')->default(false);
            $table->boolean('is_batch_tracked')->default(false);
            $table->boolean('track_stock_alerts')->default(true);
            $table->boolean('is_taxable')->default(true);
            $table->boolean('allow_backorder')->default(false);
            $table->boolean('requires_approval')->default(false);
            $table->boolean('is_perishable')->default(false);

            // Media
            $table->string('thumbnail', 500)->nullable();
            $table->string('image', 500)->nullable();
            $table->json('images')->nullable();
            $table->json('gallery')->nullable();

            // Dimensions and physical properties
            $table->decimal('length', 10, 2)->nullable();
            $table->decimal('width', 10, 2)->nullable();
            $table->decimal('height', 10, 2)->nullable();
            $table->decimal('weight', 10, 4)->nullable();
            $table->string('weight_unit', 20)->nullable();
            $table->json('dimensions')->nullable();

            // Additional fields
            $table->string('brand', 255)->nullable();
            $table->string('manufacturer', 255)->nullable();
            $table->string('model', 255)->nullable();
            $table->string('model_number', 255)->nullable();
            $table->string('origin_country', 100)->nullable();
            $table->string('hs_code', 50)->nullable();
            $table->json('custom_fields')->nullable();
            $table->json('attributes')->nullable();
            $table->json('extra_attributes')->nullable();
            $table->text('notes')->nullable();

            // Dates for perishable items
            $table->date('manufacture_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->integer('shelf_life_days')->nullable();

            // Warranty
            $table->boolean('has_warranty')->default(false);
            $table->integer('warranty_months')->nullable();
            $table->integer('warranty_period_days')->nullable();
            $table->string('warranty_period', 100)->nullable();
            $table->string('warranty_type', 100)->nullable();
            $table->text('warranty_terms')->nullable();

            // Service products
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->integer('service_duration')->nullable();
            $table->string('duration_unit', 50)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['branch_id', 'is_active']);
            $table->index(['category_id', 'is_active']);
            $table->index(['type', 'is_active']);
            $table->index('stock_quantity');
        });

        // Add fulltext index for MySQL only
        if (config('database.default') === 'mysql') {
            Schema::table('products', function (Blueprint $table) {
                $table->fullText(['name', 'name_ar', 'sku', 'barcode']);
            });
        }

        // Product price tiers
        Schema::create('product_price_tiers', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('price_group_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 18, 4);
            $table->decimal('min_quantity', 18, 4)->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'price_group_id', 'min_quantity'], 'price_tiers_prod_group_qty_unq');
        });

        // Product variations
        Schema::create('product_variations', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('sku', 100)->unique();
            $table->string('barcode', 100)->nullable();
            $table->decimal('cost_price', 18, 4)->nullable();
            $table->decimal('selling_price', 18, 4)->nullable();
            $table->decimal('stock_quantity', 18, 4)->default(0);
            $table->json('attributes')->nullable();
            $table->string('image', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'is_active']);
        });

        // Inventory batches
        Schema::create('inventory_batches', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete(); // Branch field
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number', 100);
            $table->decimal('quantity', 18, 4)->default(0);
            $table->decimal('unit_cost', 18, 4)->nullable(); // Unit cost field
            $table->decimal('cost_price', 18, 4)->nullable();
            $table->date('manufacturing_date')->nullable();
            $table->date('expiry_date')->nullable()->index();
            $table->string('supplier_batch_ref', 100)->nullable(); // Supplier batch reference
            $table->string('supplier_batch', 100)->nullable();
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete(); // Purchase reference
            $table->string('status', 50)->default('active'); // Status field
            $table->text('notes')->nullable(); // Notes field
            $table->json('meta')->nullable(); // Meta field
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'warehouse_id', 'batch_number']);
            $table->index(['product_id', 'expiry_date']);
        });

        // Inventory serials
        Schema::create('inventory_serials', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('batch_id')->nullable()
                ->constrained('inventory_batches')
                ->nullOnDelete();
            $table->string('serial_number', 100);
            $table->string('status', 50)->default('available'); // available, sold, reserved, damaged
            $table->decimal('cost_price', 18, 4)->nullable();
            $table->foreignId('sold_to_customer_id')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'serial_number']);
            $table->index(['status', 'warehouse_id']);
        });

        // Stock movements
        Schema::create('stock_movements', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()
                ->constrained('inventory_batches')
                ->nullOnDelete();
            $table->string('movement_type', 50); // purchase, sale, transfer, adjustment, return
            $table->string('reference_type', 255)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->decimal('stock_before', 18, 4);
            $table->decimal('stock_after', 18, 4);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'created_at']);
            $table->index(['warehouse_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('movement_type');
        });

        // Stock adjustments
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('reference_number', 100)->unique();
            $table->string('adjustment_type', 50); // inventory_count, damage, loss, correction
            $table->string('status', 50)->default('pending'); // pending, approved, rejected
            $table->text('reason')->nullable();
            $table->decimal('total_adjustment_value', 18, 4)->default(0);
            $table->foreignId('approved_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
        });

        // Adjustment items
        Schema::create('adjustment_items', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('adjustment_id')->constrained('stock_adjustments')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('system_quantity', 18, 4);
            $table->decimal('counted_quantity', 18, 4);
            $table->decimal('difference', 18, 4);
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['adjustment_id', 'product_id']);
        });

        // Transfers
        Schema::create('transfers', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('reference_number', 100)->unique();
            $table->foreignId('from_warehouse_id')->constrained('warehouses');
            $table->foreignId('to_warehouse_id')->constrained('warehouses');
            $table->string('status', 50)->default('pending'); // pending, in_transit, completed, cancelled
            $table->text('notes')->nullable();
            $table->decimal('total_value', 18, 4)->default(0);
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('received_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
        });

        // Transfer items
        Schema::create('transfer_items', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('received_quantity', 18, 4)->nullable();
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['transfer_id', 'product_id']);
        });

        // Low stock alerts
        Schema::create('low_stock_alerts', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->nullable()
                ->constrained('branches')
                ->nullOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->decimal('current_stock', 18, 4);
            $table->decimal('alert_threshold', 18, 4);
            $table->string('status', 50)->default('active'); // active, acknowledged, resolved
            $table->foreignId('acknowledged_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('resolved_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['branch_id', 'status', 'created_at'], 'idx_alerts_branch_status_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('low_stock_alerts');
        Schema::dropIfExists('transfer_items');
        Schema::dropIfExists('transfers');
        Schema::dropIfExists('adjustment_items');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('inventory_serials');
        Schema::dropIfExists('inventory_batches');
        Schema::dropIfExists('product_variations');
        Schema::dropIfExists('product_price_tiers');
        Schema::dropIfExists('products');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('taxes');
        Schema::dropIfExists('price_groups');
        Schema::dropIfExists('units_of_measure');
        Schema::dropIfExists('product_categories');
    }
};
