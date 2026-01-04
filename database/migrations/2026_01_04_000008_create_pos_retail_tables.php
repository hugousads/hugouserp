<?php

declare(strict_types=1);

/**
 * Consolidated POS & Retail Tables Migration
 * 
 * MySQL 8.4 Optimized:
 * - POS sessions, terminals
 * - E-commerce integrations
 * - Loyalty programs
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
        // POS sessions
        Schema::create('pos_sessions', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('session_number', 100)->unique();
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->string('status', 50)->default('open'); // open, closed
            
            // Cash management
            $table->decimal('opening_cash', 18, 4)->default(0);
            $table->decimal('closing_cash', 18, 4)->nullable();
            $table->decimal('expected_cash', 18, 4)->nullable();
            $table->decimal('cash_difference', 18, 4)->nullable();
            
            // Totals
            $table->integer('total_transactions')->default(0);
            $table->decimal('total_sales', 18, 4)->default(0);
            $table->decimal('total_refunds', 18, 4)->default(0);
            $table->decimal('total_discounts', 18, 4)->default(0);
            $table->decimal('total_taxes', 18, 4)->default(0);
            
            // Payment breakdown
            $table->decimal('total_cash', 18, 4)->default(0);
            $table->decimal('total_card', 18, 4)->default(0);
            $table->decimal('total_other', 18, 4)->default(0);
            
            $table->text('notes')->nullable();
            $table->json('breakdown')->nullable();
            
            $table->foreignId('closed_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->timestamps();
            
            $table->index(['branch_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        // Stores (e-commerce platforms)
        Schema::create('stores', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('platform', 50); // shopify, woocommerce, custom
            $table->string('store_url', 500)->nullable();
            $table->string('status', 50)->default('active');
            $table->json('settings')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Store integration credentials
        Schema::create('store_integrations', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('api_key', 500)->nullable();
            $table->string('api_secret', 500)->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('webhook_settings')->nullable();
            $table->timestamps();
        });

        // Store tokens
        Schema::create('store_tokens', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('token_type', 50);
            $table->text('token_value');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // Store orders
        Schema::create('store_orders', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 255);
            $table->string('order_number', 100);
            $table->foreignId('customer_id')->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('sale_id')->nullable()
                ->constrained()
                ->nullOnDelete();
            
            // Status
            $table->string('status', 50)->default('pending');
            $table->string('fulfillment_status', 50)->nullable();
            $table->string('payment_status', 50)->nullable();
            
            // Financial
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);
            $table->decimal('shipping_amount', 18, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('total_amount', 18, 4)->default(0);
            $table->string('currency', 3)->default('EGP');
            
            // Customer info (denormalized for sync)
            $table->string('customer_name', 255)->nullable();
            $table->string('customer_email', 255)->nullable();
            $table->string('customer_phone', 50)->nullable();
            $table->text('shipping_address')->nullable();
            $table->text('billing_address')->nullable();
            
            // Order details
            $table->json('line_items')->nullable();
            $table->json('raw_data')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['store_id', 'external_id']);
            $table->index(['store_id', 'status']);
        });

        // Store sync logs
        Schema::create('store_sync_logs', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('sync_type', 50); // orders, products, inventory, customers
            $table->string('direction', 20); // import, export
            $table->string('status', 50); // started, completed, failed
            $table->integer('records_processed')->default(0);
            $table->integer('records_succeeded')->default(0);
            $table->integer('records_failed')->default(0);
            $table->json('errors')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // Product-Store mappings
        Schema::create('product_store_mappings', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 255)->nullable();
            $table->string('external_sku', 100)->nullable();
            $table->boolean('sync_enabled')->default(true);
            $table->boolean('sync_price')->default(true);
            $table->boolean('sync_stock')->default(true);
            $table->decimal('price_adjustment', 18, 4)->default(0);
            $table->decimal('price_markup_percent', 5, 2)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['product_id', 'store_id']);
        });

        // Loyalty settings
        Schema::create('loyalty_settings', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('program_name', 255);
            $table->boolean('is_active')->default(true);
            $table->decimal('points_per_currency', 8, 4)->default(1);
            $table->decimal('currency_per_point', 8, 4)->default(0.01);
            $table->integer('min_points_to_redeem')->default(100);
            $table->integer('points_expiry_days')->nullable();
            $table->json('tier_settings')->nullable();
            $table->json('bonus_rules')->nullable();
            $table->timestamps();
        });

        // Loyalty transactions
        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('type', 50); // earned, redeemed, expired, adjusted
            $table->integer('points');
            $table->integer('balance_after');
            $table->text('description')->nullable();
            $table->date('expiry_date')->nullable();
            
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('loyalty_settings');
        Schema::dropIfExists('product_store_mappings');
        Schema::dropIfExists('store_sync_logs');
        Schema::dropIfExists('store_orders');
        Schema::dropIfExists('store_tokens');
        Schema::dropIfExists('store_integrations');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('pos_sessions');
    }
};
