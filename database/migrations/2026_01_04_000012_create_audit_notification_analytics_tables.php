<?php

declare(strict_types=1);

/**
 * Consolidated Audit, Notification & Analytics Tables Migration
 * 
 * MySQL 8.4 Optimized:
 * - Audit logging
 * - Notifications
 * - Reports & Analytics
 * - Dashboard widgets
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
        // Audit logs (Spatie Activity Log compatible)
        Schema::create('audit_logs', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->string('log_name', 255)->nullable()->index();
            $table->text('description');
            $table->string('subject_type', 255)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('causer_type', 255)->nullable();
            $table->unsignedBigInteger('causer_id')->nullable();
            $table->string('event', 255)->nullable()->index();
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable()->index();
            
            // Additional tracking
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->foreignId('branch_id')->nullable();
            
            // Auditable fields (for enhanced tracking)
            $table->string('auditable_type', 255)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            
            $table->timestamps();
            
            $table->index(['subject_type', 'subject_id']);
            $table->index(['causer_type', 'causer_id']);
            $table->index(['auditable_type', 'auditable_id']);
            $table->index('created_at');
        });

        // Notifications
        Schema::create('notifications', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->uuid('id')->primary();
            $table->string('type', 255);
            $table->string('notifiable_type', 255);
            $table->unsignedBigInteger('notifiable_id');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            
            $table->index(['notifiable_type', 'notifiable_id']);
            $table->index(['type', 'read_at']);
        });

        // Report definitions
        Schema::create('report_definitions', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->string('name', 255);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->string('category', 100)->nullable()->index();
            $table->string('data_source', 255);
            $table->json('columns')->nullable();
            $table->json('filters')->nullable();
            $table->json('grouping')->nullable();
            $table->json('sorting')->nullable();
            $table->json('calculations')->nullable();
            $table->json('chart_config')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->timestamps();
        });

        // Report templates
        Schema::create('report_templates', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('report_definition_id')->nullable()
                ->constrained('report_definitions')
                ->nullOnDelete();
            $table->string('name', 255);
            $table->string('format', 50)->default('pdf'); // pdf, excel, csv
            $table->string('page_size', 20)->default('A4');
            $table->string('orientation', 20)->default('portrait');
            $table->json('header_config')->nullable();
            $table->json('footer_config')->nullable();
            $table->json('style_config')->nullable();
            $table->text('custom_css')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // Scheduled reports
        Schema::create('scheduled_reports', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 255);
            $table->foreignId('report_definition_id')->nullable()
                ->constrained('report_definitions')
                ->nullOnDelete();
            $table->json('parameters')->nullable();
            $table->string('format', 50)->default('pdf');
            $table->string('schedule_type', 50); // daily, weekly, monthly
            $table->string('schedule_time', 10)->nullable();
            $table->json('schedule_days')->nullable();
            $table->integer('schedule_day_of_month')->nullable();
            $table->json('recipients')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
        });

        // Report schedules (execution log)
        Schema::create('report_schedules', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('scheduled_report_id')
                ->constrained('scheduled_reports')
                ->cascadeOnDelete();
            $table->string('status', 50)->default('pending'); // pending, running, completed, failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        // Saved report views
        Schema::create('saved_report_views', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_definition_id')->nullable()
                ->constrained('report_definitions')
                ->cascadeOnDelete();
            $table->string('name', 255);
            $table->json('filters')->nullable();
            $table->json('columns')->nullable();
            $table->json('sorting')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // Export layouts (custom export configurations)
        Schema::create('export_layouts', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('model_type', 255);
            $table->string('name', 255);
            $table->json('columns')->nullable();
            $table->json('formatting')->nullable();
            $table->string('default_format', 50)->default('xlsx');
            $table->boolean('is_default')->default(false);
            
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->timestamps();
            
            $table->unique(['branch_id', 'model_type', 'name']);
        });

        // Dashboard widgets
        Schema::create('dashboard_widgets', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->string('name', 255);
            $table->string('slug', 100)->unique();
            $table->string('type', 50); // chart, counter, table, list
            $table->string('data_source', 255);
            $table->json('config')->nullable();
            $table->json('default_settings')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // User dashboard layouts
        Schema::create('user_dashboard_layouts', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('dashboard_type', 50)->default('main');
            $table->json('layout')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            
            $table->unique(['user_id', 'dashboard_type']);
        });

        // User dashboard widgets
        Schema::create('user_dashboard_widgets', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('layout_id')
                ->constrained('user_dashboard_layouts')
                ->cascadeOnDelete();
            $table->foreignId('widget_id')
                ->constrained('dashboard_widgets')
                ->cascadeOnDelete();
            $table->integer('position_x')->default(0);
            $table->integer('position_y')->default(0);
            $table->integer('width')->default(1);
            $table->integer('height')->default(1);
            $table->json('settings')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        // Widget data cache
        Schema::create('widget_data_cache', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('widget_id')
                ->constrained('dashboard_widgets')
                ->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cache_key', 255);
            $table->json('data')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            
            $table->unique(['widget_id', 'branch_id', 'cache_key']);
        });

        // User preferences
        Schema::create('user_preferences', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 255);
            $table->text('value')->nullable();
            $table->timestamps();
            
            $table->unique(['user_id', 'key']);
        });

        // User favorites
        Schema::create('user_favorites', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('favoritable_type', 255);
            $table->unsignedBigInteger('favoritable_id');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->unique(['user_id', 'favoritable_type', 'favoritable_id']);
            $table->index(['favoritable_type', 'favoritable_id']);
        });

        // Search history
        Schema::create('search_history', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('query', 500);
            $table->string('context', 100)->nullable(); // products, customers, etc.
            $table->integer('result_count')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['user_id', 'created_at']);
        });

        // Search index (for global search)
        Schema::create('search_index', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('searchable_type', 255);
            $table->unsignedBigInteger('searchable_id');
            $table->string('title', 500);
            $table->text('content')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('indexed_at')->useCurrent();
            
            $table->index(['searchable_type', 'searchable_id']);
            $table->fullText(['title', 'content']);
        });

        // Alert rules
        Schema::create('alert_rules', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('type', 50); // threshold, condition, schedule
            $table->string('entity_type', 255);
            $table->json('conditions')->nullable();
            $table->json('actions')->nullable();
            $table->string('severity', 50)->default('info'); // info, warning, critical
            $table->boolean('is_active')->default(true);
            $table->integer('cooldown_minutes')->default(60);
            $table->timestamp('last_triggered_at')->nullable();
            
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->timestamps();
        });

        // Alert recipients
        Schema::create('alert_recipients', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('alert_rule_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()
                ->constrained()
                ->cascadeOnDelete();
            $table->string('email', 255)->nullable();
            $table->string('notification_channel', 50)->default('database'); // database, email, sms
            $table->timestamps();
        });

        // Alert instances
        Schema::create('alert_instances', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('alert_rule_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('entity_type', 255)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->text('message');
            $table->json('data')->nullable();
            $table->string('status', 50)->default('active'); // active, acknowledged, resolved
            $table->foreignId('acknowledged_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            
            $table->index(['alert_rule_id', 'status']);
        });

        // Anomaly baselines (for anomaly detection)
        Schema::create('anomaly_baselines', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('metric_name', 255);
            $table->string('period', 50); // hourly, daily, weekly
            $table->integer('period_value')->nullable();
            $table->decimal('baseline_value', 18, 4);
            $table->decimal('std_deviation', 18, 4)->nullable();
            $table->decimal('min_value', 18, 4)->nullable();
            $table->decimal('max_value', 18, 4)->nullable();
            $table->integer('sample_count')->default(0);
            $table->timestamp('calculated_at')->useCurrent();
            
            $table->unique(['branch_id', 'metric_name', 'period', 'period_value']);
        });

        // Cashflow projections
        Schema::create('cashflow_projections', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->date('projection_date');
            $table->string('type', 50); // income, expense
            $table->string('category', 100)->nullable();
            $table->decimal('projected_amount', 18, 4);
            $table->decimal('actual_amount', 18, 4)->nullable();
            $table->decimal('variance', 18, 4)->nullable();
            $table->string('source', 255)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['branch_id', 'projection_date']);
            $table->index(['type', 'projection_date']);
        });

        // Financial report configs
        Schema::create('financial_report_configs', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('report_type', 100); // balance_sheet, income_statement, cashflow
            $table->string('name', 255);
            $table->json('account_mappings')->nullable();
            $table->json('layout_config')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // Aging configurations
        Schema::create('aging_configurations', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 50); // receivables, payables
            $table->json('periods')->nullable(); // e.g., [30, 60, 90, 120]
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // Workflow definitions
        Schema::create('workflow_definitions', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 255);
            $table->string('entity_type', 255);
            $table->string('trigger_event', 100);
            $table->json('conditions')->nullable();
            $table->json('steps')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);
            
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
        });

        // Workflow rules
        Schema::create('workflow_rules', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('workflow_id')
                ->constrained('workflow_definitions')
                ->cascadeOnDelete();
            $table->string('name', 255);
            $table->integer('sequence')->default(0);
            $table->string('action_type', 100);
            $table->json('action_config')->nullable();
            $table->json('conditions')->nullable();
            $table->timestamps();
        });

        // Workflow instances
        Schema::create('workflow_instances', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('workflow_id')
                ->constrained('workflow_definitions')
                ->cascadeOnDelete();
            $table->string('entity_type', 255);
            $table->unsignedBigInteger('entity_id');
            $table->string('status', 50)->default('active'); // active, completed, cancelled
            $table->integer('current_step')->default(0);
            $table->json('context')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['entity_type', 'entity_id']);
        });

        // Workflow approvals
        Schema::create('workflow_approvals', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('instance_id')
                ->constrained('workflow_instances')
                ->cascadeOnDelete();
            $table->integer('step_number');
            $table->foreignId('approver_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('status', 50)->default('pending'); // pending, approved, rejected
            $table->text('comments')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            
            $table->index(['instance_id', 'status']);
        });

        // Workflow notifications
        Schema::create('workflow_notifications', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('instance_id')
                ->constrained('workflow_instances')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('type', 50);
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // Workflow audit logs
        Schema::create('workflow_audit_logs', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('instance_id')
                ->constrained('workflow_instances')
                ->cascadeOnDelete();
            $table->string('action', 100);
            $table->foreignId('user_id')->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // Media table (Spatie Media Library compatible)
        Schema::create('media', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->string('model_type', 255);
            $table->unsignedBigInteger('model_id');
            $table->uuid('uuid')->nullable()->unique();
            $table->string('collection_name', 255);
            $table->string('name', 255);
            $table->string('file_name', 255);
            $table->string('mime_type', 255)->nullable();
            $table->string('disk', 255);
            $table->string('conversions_disk', 255)->nullable();
            $table->unsignedBigInteger('size');
            $table->json('manipulations');
            $table->json('custom_properties');
            $table->json('generated_conversions');
            $table->json('responsive_images');
            $table->unsignedInteger('order_column')->nullable()->index();
            $table->nullableTimestamps();
            
            $table->index(['model_type', 'model_id']);
        });

        // Warranties
        Schema::create('warranties', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('sale_id')->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('customer_id')->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('serial_number', 100)->nullable();
            $table->string('warranty_number', 100)->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 50)->default('active'); // active, expired, claimed, void
            $table->text('terms')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['serial_number', 'status']);
            $table->index(['customer_id', 'status']);
        });

        // Product compatibilities
        Schema::create('product_compatibilities', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('compatible_product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->string('compatibility_type', 50)->default('compatible');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->unique(['product_id', 'compatible_product_id']);
        });

        // Module product fields
        Schema::create('module_product_fields', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('name_ar', 255)->nullable();
            $table->string('field_key', 100);
            $table->string('field_type', 50);
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['module_id', 'field_key']);
        });

        // Product field values
        Schema::create('product_field_values', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('field_id')
                ->constrained('module_product_fields')
                ->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();
            
            $table->unique(['product_id', 'field_id']);
        });

        // Branch admins
        Schema::create('branch_admins', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            
            $table->unique(['branch_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_admins');
        Schema::dropIfExists('product_field_values');
        Schema::dropIfExists('module_product_fields');
        Schema::dropIfExists('product_compatibilities');
        Schema::dropIfExists('warranties');
        Schema::dropIfExists('media');
        Schema::dropIfExists('workflow_audit_logs');
        Schema::dropIfExists('workflow_notifications');
        Schema::dropIfExists('workflow_approvals');
        Schema::dropIfExists('workflow_instances');
        Schema::dropIfExists('workflow_rules');
        Schema::dropIfExists('workflow_definitions');
        Schema::dropIfExists('aging_configurations');
        Schema::dropIfExists('financial_report_configs');
        Schema::dropIfExists('cashflow_projections');
        Schema::dropIfExists('anomaly_baselines');
        Schema::dropIfExists('alert_instances');
        Schema::dropIfExists('alert_recipients');
        Schema::dropIfExists('alert_rules');
        Schema::dropIfExists('search_index');
        Schema::dropIfExists('search_history');
        Schema::dropIfExists('user_favorites');
        Schema::dropIfExists('user_preferences');
        Schema::dropIfExists('widget_data_cache');
        Schema::dropIfExists('user_dashboard_widgets');
        Schema::dropIfExists('user_dashboard_layouts');
        Schema::dropIfExists('dashboard_widgets');
        Schema::dropIfExists('export_layouts');
        Schema::dropIfExists('saved_report_views');
        Schema::dropIfExists('report_schedules');
        Schema::dropIfExists('scheduled_reports');
        Schema::dropIfExists('report_templates');
        Schema::dropIfExists('report_definitions');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('audit_logs');
    }
};
