<?php

declare(strict_types=1);

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
        // Dashboard Widgets - Available widget types
        Schema::create('dashboard_widgets', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // sales_chart, inventory_summary, etc.
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->text('description')->nullable();
            $table->string('component'); // Livewire component name
            $table->string('icon')->nullable();
            $table->enum('category', ['sales', 'inventory', 'hrm', 'accounting', 'analytics', 'general'])->default('general');
            $table->json('default_settings')->nullable(); // Default configuration
            $table->json('configurable_options')->nullable(); // What can user configure
            $table->integer('default_width')->default(6); // Grid columns (1-12)
            $table->integer('default_height')->default(4); // Grid rows
            $table->integer('min_width')->default(3);
            $table->integer('min_height')->default(2);
            $table->boolean('requires_permission')->default(false);
            $table->string('permission_key')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('category');
            $table->index(['is_active', 'sort_order']);
        });

        // User Dashboard Layouts - User-specific dashboard configuration
        Schema::create('user_dashboard_layouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->default('Default Dashboard');
            $table->boolean('is_default')->default(true);
            $table->json('layout_config')->nullable(); // Grid layout configuration
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
            $table->index('branch_id');
        });

        // User Dashboard Widget Instances - User's active widgets
        Schema::create('user_dashboard_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_dashboard_layout_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dashboard_widget_id')->constrained()->cascadeOnDelete();
            $table->integer('position_x')->default(0); // Grid column position
            $table->integer('position_y')->default(0); // Grid row position
            $table->integer('width')->default(6); // How many columns
            $table->integer('height')->default(4); // How many rows
            $table->json('settings')->nullable(); // User-specific widget settings
            $table->boolean('is_visible')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('user_dashboard_layout_id');
            $table->index(['dashboard_widget_id', 'is_visible']);
        });

        // Widget Data Cache - Cache widget data for performance
        Schema::create('widget_data_cache', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dashboard_widget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->json('data'); // Cached widget data
            $table->timestamp('cached_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'dashboard_widget_id', 'branch_id']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('widget_data_cache');
        Schema::dropIfExists('user_dashboard_widgets');
        Schema::dropIfExists('user_dashboard_layouts');
        Schema::dropIfExists('dashboard_widgets');
    }
};
