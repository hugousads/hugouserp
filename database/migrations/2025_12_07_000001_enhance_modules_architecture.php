<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Enhance module architecture for better modularity
     */
    public function up(): void
    {
        // Add module type and category enhancements to modules table
        if (Schema::hasTable('modules') && ! Schema::hasColumn('modules', 'module_type')) {
            Schema::table('modules', function (Blueprint $table) {
                $table->enum('module_type', ['data', 'functional', 'hybrid'])->default('hybrid')->after('category');
                $table->json('operation_config')->nullable()->after('default_settings');
                $table->json('integration_hooks')->nullable()->after('operation_config');
                $table->boolean('supports_reporting')->default(true)->after('is_active');
                $table->boolean('supports_custom_fields')->default(true)->after('supports_reporting');
            });
        }

        // Create module_policies table for system policies per module
        if (! Schema::hasTable('module_policies')) {
            Schema::create('module_policies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->string('policy_key')->index();
                $table->string('policy_name');
                $table->text('policy_description')->nullable();
                $table->json('policy_rules');
                $table->enum('scope', ['global', 'branch', 'user'])->default('branch');
                $table->boolean('is_active')->default(true);
                $table->integer('priority')->default(100);
                $table->timestamps();

                $table->unique(['module_id', 'branch_id', 'policy_key'], 'module_policy_unique');
                $table->index(['module_id', 'policy_key']);
            });
        }

        // Create module_operations table for operation mappings
        if (! Schema::hasTable('module_operations')) {
            Schema::create('module_operations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
                $table->string('operation_key')->index();
                $table->string('operation_name');
                $table->text('description')->nullable();
                $table->enum('operation_type', ['create', 'read', 'update', 'delete', 'export', 'import', 'custom'])->default('custom');
                $table->json('operation_config')->nullable();
                $table->json('required_permissions')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(100);
                $table->timestamps();

                $table->unique(['module_id', 'operation_key']);
            });
        }

        // Create module_navigation table for sidebar hierarchy
        if (! Schema::hasTable('module_navigation')) {
            Schema::create('module_navigation', function (Blueprint $table) {
                $table->id();
                $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('module_navigation')->nullOnDelete();
                $table->string('nav_key')->index();
                $table->string('nav_label');
                $table->string('nav_label_ar')->nullable();
                $table->string('route_name')->nullable();
                $table->string('icon')->nullable();
                $table->json('required_permissions')->nullable();
                $table->json('visibility_conditions')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(100);
                $table->timestamps();

                $table->unique(['module_id', 'nav_key']);
                $table->index(['module_id', 'parent_id']);
            });
        }

        // Enhance branch_modules with constraints and inheritance
        if (Schema::hasTable('branch_modules') && ! Schema::hasColumn('branch_modules', 'activation_constraints')) {
            Schema::table('branch_modules', function (Blueprint $table) {
                $table->json('activation_constraints')->nullable()->after('settings');
                $table->json('permission_overrides')->nullable()->after('activation_constraints');
                $table->boolean('inherit_settings')->default(true)->after('enabled');
                $table->timestamp('activated_at')->nullable()->after('updated_at');
            });
        }

        // Enhance module_settings with scope and inheritance
        if (Schema::hasTable('module_settings') && ! Schema::hasColumn('module_settings', 'scope')) {
            Schema::table('module_settings', function (Blueprint $table) {
                $table->enum('scope', ['global', 'branch', 'user'])->default('branch')->after('setting_type');
                $table->boolean('is_inherited')->default(false)->after('scope');
                $table->foreignId('inherited_from_setting_id')->nullable()->constrained('module_settings')->nullOnDelete()->after('is_inherited');
                $table->boolean('is_system')->default(false)->after('inherited_from_setting_id');
                $table->integer('priority')->default(100)->after('is_system');

                $table->index(['module_id', 'branch_id', 'setting_key']);
            });
        }

        // Enhance module_fields with advanced capabilities
        if (Schema::hasTable('module_fields') && ! Schema::hasColumn('module_fields', 'field_category')) {
            Schema::table('module_fields', function (Blueprint $table) {
                $table->string('field_category')->nullable()->after('entity');
                $table->json('validation_rules')->nullable()->after('rules');
                $table->json('computed_config')->nullable()->after('validation_rules');
                $table->boolean('is_system')->default(false)->after('is_visible');
                $table->boolean('is_searchable')->default(false)->after('is_system');
                $table->boolean('supports_bulk_edit')->default(false)->after('is_searchable');
                $table->json('dependencies')->nullable()->after('meta');

                $table->index(['module_key', 'entity', 'field_category']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop new tables
        Schema::dropIfExists('module_navigation');
        Schema::dropIfExists('module_operations');
        Schema::dropIfExists('module_policies');

        // Remove columns from modules
        if (Schema::hasTable('modules')) {
            Schema::table('modules', function (Blueprint $table) {
                $table->dropColumn([
                    'module_type',
                    'operation_config',
                    'integration_hooks',
                    'supports_reporting',
                    'supports_custom_fields',
                ]);
            });
        }

        // Remove columns from branch_modules
        if (Schema::hasTable('branch_modules')) {
            Schema::table('branch_modules', function (Blueprint $table) {
                $table->dropColumn([
                    'activation_constraints',
                    'permission_overrides',
                    'inherit_settings',
                    'activated_at',
                ]);
            });
        }

        // Remove columns from module_settings
        if (Schema::hasTable('module_settings')) {
            Schema::table('module_settings', function (Blueprint $table) {
                $table->dropForeign(['inherited_from_setting_id']);
                $table->dropColumn([
                    'scope',
                    'is_inherited',
                    'inherited_from_setting_id',
                    'is_system',
                    'priority',
                ]);
            });
        }

        // Remove columns from module_fields
        if (Schema::hasTable('module_fields')) {
            Schema::table('module_fields', function (Blueprint $table) {
                $table->dropColumn([
                    'field_category',
                    'validation_rules',
                    'computed_config',
                    'is_system',
                    'is_searchable',
                    'supports_bulk_edit',
                    'dependencies',
                ]);
            });
        }
    }
};
