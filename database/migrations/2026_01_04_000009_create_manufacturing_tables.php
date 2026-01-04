<?php

declare(strict_types=1);

/**
 * Consolidated Manufacturing Tables Migration
 * 
 * MySQL 8.4 Optimized:
 * - Bills of materials
 * - Production orders
 * - Work centers
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
        // Work centers
        Schema::create('work_centers', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->string('name_ar', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('type', 50)->default('machine'); // machine, assembly, manual
            $table->decimal('hourly_rate', 18, 4)->default(0);
            $table->decimal('setup_time_hours', 8, 2)->default(0);
            $table->integer('capacity_per_hour')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->string('location', 255)->nullable();
            $table->foreignId('manager_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // Bills of materials
        Schema::create('bills_of_materials', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->string('reference_number', 100)->unique();
            $table->string('name', 255)->nullable();
            $table->string('version', 20)->default('1.0');
            $table->decimal('quantity', 18, 4)->default(1);
            $table->decimal('yield_percentage', 5, 2)->default(100);
            $table->decimal('estimated_cost', 18, 4)->default(0);
            $table->decimal('estimated_time_hours', 8, 2)->default(0);
            $table->string('status', 50)->default('draft'); // draft, active, obsolete
            $table->text('notes')->nullable();
            $table->json('custom_fields')->nullable();
            
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['product_id', 'status']);
        });

        // BOM items (components)
        Schema::create('bom_items', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('bom_id')
                ->constrained('bills_of_materials')
                ->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->decimal('quantity', 18, 4);
            $table->foreignId('unit_id')->nullable()
                ->constrained('units_of_measure')
                ->nullOnDelete();
            $table->decimal('scrap_percentage', 5, 2)->default(0);
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->string('type', 50)->default('component'); // component, consumable, tool
            $table->boolean('is_optional')->default(false);
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['bom_id', 'sort_order']);
        });

        // BOM operations
        Schema::create('bom_operations', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('bom_id')
                ->constrained('bills_of_materials')
                ->cascadeOnDelete();
            $table->foreignId('work_center_id')->nullable()
                ->constrained('work_centers')
                ->nullOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->decimal('setup_time_hours', 8, 2)->default(0);
            $table->decimal('operation_time_hours', 8, 2)->default(0);
            $table->decimal('cost_per_hour', 18, 4)->default(0);
            $table->integer('sequence')->default(1);
            $table->text('instructions')->nullable();
            $table->json('quality_checks')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['bom_id', 'sequence']);
        });

        // Production orders
        Schema::create('production_orders', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bom_id')
                ->constrained('bills_of_materials');
            $table->foreignId('product_id')->constrained();
            $table->foreignId('warehouse_id')->constrained();
            $table->string('reference_number', 100)->unique();
            $table->string('status', 50)->default('draft'); // draft, planned, in_progress, completed, cancelled
            $table->string('priority', 50)->default('normal');
            
            // Quantities
            $table->decimal('planned_quantity', 18, 4);
            $table->decimal('produced_quantity', 18, 4)->default(0);
            $table->decimal('rejected_quantity', 18, 4)->default(0);
            
            // Dates
            $table->date('planned_start_date');
            $table->date('planned_end_date')->nullable();
            $table->timestamp('actual_start_date')->nullable();
            $table->timestamp('actual_end_date')->nullable();
            
            // Costs
            $table->decimal('estimated_cost', 18, 4)->default(0);
            $table->decimal('actual_cost', 18, 4)->default(0);
            $table->decimal('material_cost', 18, 4)->default(0);
            $table->decimal('labor_cost', 18, 4)->default(0);
            $table->decimal('overhead_cost', 18, 4)->default(0);
            
            // References
            $table->foreignId('sale_id')->nullable()
                ->constrained()
                ->nullOnDelete();
            
            $table->text('notes')->nullable();
            $table->json('custom_fields')->nullable();
            
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('approved_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['branch_id', 'status']);
            $table->index(['planned_start_date', 'status']);
        });

        // Production order items (material consumption)
        Schema::create('production_order_items', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('production_order_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('batch_id')->nullable()
                ->constrained('inventory_batches')
                ->nullOnDelete();
            $table->decimal('required_quantity', 18, 4);
            $table->decimal('consumed_quantity', 18, 4)->default(0);
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->string('status', 50)->default('pending'); // pending, partially_consumed, consumed
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Production order operations
        Schema::create('production_order_operations', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('production_order_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('bom_operation_id')->nullable()
                ->constrained('bom_operations')
                ->nullOnDelete();
            $table->foreignId('work_center_id')->nullable()
                ->constrained('work_centers')
                ->nullOnDelete();
            $table->string('name', 255);
            $table->integer('sequence')->default(1);
            $table->string('status', 50)->default('pending'); // pending, in_progress, completed
            
            // Times
            $table->decimal('planned_hours', 8, 2)->default(0);
            $table->decimal('actual_hours', 8, 2)->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            // Assigned
            $table->foreignId('assigned_to')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->text('notes')->nullable();
            $table->json('quality_results')->nullable();
            $table->timestamps();
            
            $table->index(['production_order_id', 'sequence']);
        });

        // Manufacturing transactions (material movements)
        Schema::create('manufacturing_transactions', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('production_order_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->string('type', 50); // consumption, production, scrap, return
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('batch_id')->nullable()
                ->constrained('inventory_batches')
                ->nullOnDelete();
            $table->text('notes')->nullable();
            
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['production_order_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacturing_transactions');
        Schema::dropIfExists('production_order_operations');
        Schema::dropIfExists('production_order_items');
        Schema::dropIfExists('production_orders');
        Schema::dropIfExists('bom_operations');
        Schema::dropIfExists('bom_items');
        Schema::dropIfExists('bills_of_materials');
        Schema::dropIfExists('work_centers');
    }
};
