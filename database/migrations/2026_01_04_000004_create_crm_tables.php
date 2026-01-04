<?php

declare(strict_types=1);

/**
 * Consolidated CRM Tables Migration
 * 
 * MySQL 8.4 Optimized:
 * - Customers and suppliers
 * - Business relationships
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
        // Customers
        Schema::create('customers', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->string('name_ar', 255)->nullable();
            $table->string('type', 50)->default('individual'); // individual, company
            
            // Contact info
            $table->string('email', 255)->nullable()->index();
            $table->string('phone', 50)->nullable()->index();
            $table->string('mobile', 50)->nullable();
            $table->string('fax', 50)->nullable();
            $table->string('website', 255)->nullable();
            
            // Address
            $table->string('address', 500)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 100)->nullable();
            $table->text('shipping_address')->nullable();
            
            // Business info
            $table->string('tax_number', 100)->nullable();
            $table->string('commercial_register', 100)->nullable();
            $table->string('national_id', 50)->nullable();
            $table->string('contact_person', 255)->nullable();
            $table->string('contact_position', 100)->nullable();
            
            // Financial
            $table->foreignId('price_group_id')->nullable()
                ->constrained('price_groups')
                ->nullOnDelete();
            $table->decimal('credit_limit', 18, 4)->nullable();
            $table->decimal('balance', 18, 4)->default(0);
            $table->integer('payment_terms_days')->nullable();
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->string('currency', 3)->nullable();
            
            // Loyalty
            $table->integer('loyalty_points')->default(0);
            $table->string('loyalty_tier', 50)->nullable();
            
            // Status
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_blocked')->default(false);
            $table->text('block_reason')->nullable();
            
            // Additional
            $table->text('notes')->nullable();
            $table->json('custom_fields')->nullable();
            $table->string('source', 100)->nullable(); // how they found us
            $table->date('birthday')->nullable();
            $table->string('gender', 20)->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['branch_id', 'is_active']);
            $table->fullText(['name', 'name_ar', 'phone', 'email']);
        });

        // Suppliers
        Schema::create('suppliers', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->string('name_ar', 255)->nullable();
            $table->string('type', 50)->default('supplier'); // supplier, manufacturer, distributor
            
            // Contact info
            $table->string('email', 255)->nullable()->index();
            $table->string('phone', 50)->nullable()->index();
            $table->string('mobile', 50)->nullable();
            $table->string('fax', 50)->nullable();
            $table->string('website', 255)->nullable();
            
            // Address
            $table->string('address', 500)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 100)->nullable();
            
            // Business info
            $table->string('tax_number', 100)->nullable();
            $table->string('commercial_register', 100)->nullable();
            $table->string('contact_person', 255)->nullable();
            $table->string('contact_position', 100)->nullable();
            $table->string('bank_name', 255)->nullable();
            $table->string('bank_account', 100)->nullable();
            $table->string('bank_iban', 50)->nullable();
            $table->string('bank_swift', 20)->nullable();
            
            // Financial
            $table->decimal('balance', 18, 4)->default(0);
            $table->integer('payment_terms_days')->nullable();
            $table->string('currency', 3)->nullable();
            $table->decimal('credit_limit', 18, 4)->nullable();
            
            // Rating & Status
            $table->unsignedTinyInteger('rating')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_preferred')->default(false);
            $table->boolean('is_blocked')->default(false);
            
            // Delivery
            $table->integer('lead_time_days')->nullable();
            $table->decimal('minimum_order_amount', 18, 4)->nullable();
            $table->decimal('shipping_cost', 18, 4)->nullable();
            
            // Additional
            $table->text('notes')->nullable();
            $table->json('custom_fields')->nullable();
            $table->json('product_categories')->nullable(); // categories they supply
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['branch_id', 'is_active']);
            $table->fullText(['name', 'name_ar', 'phone', 'email']);
        });

        // Customer attachments reference
        Schema::create('attachments', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->string('attachable_type', 255);
            $table->unsignedBigInteger('attachable_id');
            $table->string('file_name', 255);
            $table->string('file_path', 500);
            $table->string('file_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('title', 255)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('uploaded_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            
            $table->index(['attachable_type', 'attachable_id']);
        });

        // Notes system
        Schema::create('notes', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->string('notable_type', 255);
            $table->unsignedBigInteger('notable_id');
            $table->text('content');
            $table->boolean('is_private')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            
            $table->index(['notable_type', 'notable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('customers');
    }
};
