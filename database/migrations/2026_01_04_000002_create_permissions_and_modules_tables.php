<?php

declare(strict_types=1);

/**
 * Consolidated Permissions & Modules Tables Migration
 * 
 * MySQL 8.4 Optimized:
 * - Spatie Permission compatible
 * - Module management system
 * - Proper indexing for role checks
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
        // Permissions table (Spatie compatible)
        Schema::create('permissions', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->string('name', 255);
            $table->string('guard_name', 255)->default('web');
            $table->string('group', 100)->nullable();
            $table->string('description', 500)->nullable();
            $table->timestamps();
            
            $table->unique(['name', 'guard_name']);
            $table->index('group');
        });

        // Roles table (Spatie compatible)
        Schema::create('roles', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->string('name', 255);
            $table->string('guard_name', 255)->default('web');
            $table->string('display_name', 255)->nullable();
            $table->string('description', 500)->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            
            $table->unique(['name', 'guard_name']);
        });

        // Role-Permission pivot
        Schema::create('role_has_permissions', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            
            $table->primary(['permission_id', 'role_id']);
        });

        // Model-Permission pivot
        Schema::create('model_has_permissions', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->string('model_type', 255);
            $table->unsignedBigInteger('model_id');
            
            $table->primary(['permission_id', 'model_id', 'model_type']);
            $table->index(['model_id', 'model_type']);
        });

        // Model-Role pivot
        Schema::create('model_has_roles', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('model_type', 255);
            $table->unsignedBigInteger('model_id');
            
            $table->primary(['role_id', 'model_id', 'model_type']);
            $table->index(['model_id', 'model_type']);
        });

        // Modules table
        Schema::create('modules', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->string('name', 255);
            $table->string('name_ar', 255)->nullable();
            $table->string('slug', 100)->unique();
            $table->string('icon', 100)->nullable();
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_core')->default(false);
            $table->boolean('supports_items')->default(false);
            $table->integer('sort_order')->default(0);
            $table->string('version', 20)->nullable();
            $table->json('settings')->nullable();
            $table->json('permissions')->nullable();
            $table->json('dependencies')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['is_active', 'sort_order']);
        });

        // Branch-Module pivot
        Schema::create('branch_modules', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
            
            $table->unique(['branch_id', 'module_id']);
        });

        // Module custom fields
        Schema::create('module_fields', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('name_ar', 255)->nullable();
            $table->string('field_key', 100);
            $table->string('field_type', 50); // text, number, date, select, etc.
            $table->json('options')->nullable();
            $table->json('validation_rules')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->unique(['module_id', 'field_key']);
        });

        // Module navigation items
        Schema::create('module_navigation', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('module_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('label', 255);
            $table->string('label_ar', 255)->nullable();
            $table->string('route', 255)->nullable();
            $table->string('icon', 100)->nullable();
            $table->foreignId('parent_id')->nullable()
                ->constrained('module_navigation')
                ->cascadeOnDelete();
            $table->string('permission', 255)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['module_id', 'sort_order']);
        });

        // Module settings
        Schema::create('module_settings', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->string('key', 255);
            $table->text('value')->nullable();
            $table->string('type', 50)->default('string');
            $table->timestamps();
            
            $table->unique(['module_id', 'key']);
        });

        // Module operations audit
        Schema::create('module_operations', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('operation', 100);
            $table->string('model_type', 255)->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['module_id', 'created_at']);
            $table->index(['model_type', 'model_id']);
        });

        // Module policies
        Schema::create('module_policies', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->string('policy_key', 100);
            $table->text('policy_value')->nullable();
            $table->boolean('is_enforced')->default(true);
            $table->timestamps();
            
            $table->unique(['module_id', 'policy_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_policies');
        Schema::dropIfExists('module_operations');
        Schema::dropIfExists('module_settings');
        Schema::dropIfExists('module_navigation');
        Schema::dropIfExists('module_fields');
        Schema::dropIfExists('branch_modules');
        Schema::dropIfExists('modules');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
