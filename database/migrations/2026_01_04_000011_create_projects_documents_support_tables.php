<?php

declare(strict_types=1);

/**
 * Consolidated Projects, Documents & Support Tables Migration
 * 
 * MySQL 8.4 Optimized:
 * - Project management
 * - Document management
 * - Helpdesk/Tickets
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
        // Projects
        Schema::create('projects', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->string('name_ar', 255)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('customer_id')->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('manager_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            // Status & Dates
            $table->string('status', 50)->default('planning'); // planning, active, on_hold, completed, cancelled
            $table->string('priority', 50)->default('normal');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->decimal('progress_percent', 5, 2)->default(0);
            
            // Financial
            $table->decimal('budget', 18, 4)->nullable();
            $table->decimal('actual_cost', 18, 4)->default(0);
            $table->string('billing_type', 50)->nullable(); // fixed, hourly, milestone
            $table->decimal('hourly_rate', 18, 4)->nullable();
            
            // Tags & Categories
            $table->string('category', 100)->nullable();
            $table->json('tags')->nullable();
            
            $table->text('notes')->nullable();
            $table->json('custom_fields')->nullable();
            
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['branch_id', 'status']);
            $table->index(['manager_id', 'status']);
        });

        // Project milestones
        Schema::create('project_milestones', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->string('status', 50)->default('pending');
            $table->decimal('amount', 18, 4)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index(['project_id', 'status']);
        });

        // Project tasks
        Schema::create('project_tasks', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('milestone_id')->nullable()
                ->constrained('project_milestones')
                ->nullOnDelete();
            $table->foreignId('parent_id')->nullable()
                ->constrained('project_tasks')
                ->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('status', 50)->default('todo'); // todo, in_progress, review, done
            $table->string('priority', 50)->default('normal');
            $table->foreignId('assigned_to')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            // Dates & Estimates
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->decimal('estimated_hours', 8, 2)->nullable();
            $table->decimal('actual_hours', 8, 2)->default(0);
            $table->decimal('progress_percent', 5, 2)->default(0);
            
            $table->integer('sort_order')->default(0);
            $table->json('tags')->nullable();
            
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['project_id', 'status']);
            $table->index(['assigned_to', 'status']);
        });

        // Task dependencies
        Schema::create('task_dependencies', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('task_id')
                ->constrained('project_tasks')
                ->cascadeOnDelete();
            $table->foreignId('depends_on_id')
                ->constrained('project_tasks')
                ->cascadeOnDelete();
            $table->string('type', 50)->default('finish_to_start');
            $table->timestamps();
            
            $table->unique(['task_id', 'depends_on_id']);
        });

        // Time logs
        Schema::create('project_time_logs', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()
                ->constrained('project_tasks')
                ->nullOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->date('log_date');
            $table->decimal('hours', 8, 2);
            $table->text('description')->nullable();
            $table->boolean('is_billable')->default(true);
            $table->decimal('hourly_rate', 18, 4)->nullable();
            $table->string('status', 50)->default('pending'); // pending, approved, invoiced
            
            $table->foreignId('approved_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->timestamps();
            
            $table->index(['project_id', 'log_date']);
            $table->index(['user_id', 'log_date']);
        });

        // Project expenses
        Schema::create('project_expenses', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_id')->nullable()
                ->constrained('expenses')
                ->nullOnDelete();
            $table->string('description', 500);
            $table->decimal('amount', 18, 4);
            $table->date('expense_date');
            $table->string('category', 100)->nullable();
            $table->boolean('is_billable')->default(true);
            $table->string('status', 50)->default('pending'); // pending, approved, rejected
            $table->text('rejection_reason')->nullable();
            $table->json('attachments')->nullable();
            
            $table->foreignId('submitted_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('approved_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
        });

        // Document tags
        Schema::create('document_tags', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->string('color', 20)->nullable();
            $table->timestamps();
            
            $table->unique(['branch_id', 'name']);
        });

        // Documents
        Schema::create('documents', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('title', 255);
            $table->string('title_ar', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('file_name', 255);
            $table->string('file_path', 500);
            $table->string('file_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();
            
            // Categorization
            $table->string('category', 100)->nullable();
            $table->string('folder_path', 500)->nullable();
            $table->foreignId('parent_id')->nullable()
                ->constrained('documents')
                ->cascadeOnDelete();
            
            // Associations
            $table->string('documentable_type', 255)->nullable();
            $table->unsignedBigInteger('documentable_id')->nullable();
            
            // Access control
            $table->string('visibility', 50)->default('private'); // private, branch, public
            $table->boolean('is_locked')->default(false);
            $table->string('password_hash', 255)->nullable();
            
            // Version tracking
            $table->integer('version')->default(1);
            $table->boolean('is_current_version')->default(true);
            $table->foreignId('original_document_id')->nullable()
                ->constrained('documents')
                ->nullOnDelete();
            
            // Dates
            $table->date('expiry_date')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            
            $table->foreignId('uploaded_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['branch_id', 'category']);
            $table->index(['documentable_type', 'documentable_id']);
            $table->fullText(['title', 'title_ar', 'description']);
        });

        // Document-Tag pivot
        Schema::create('document_tag', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('document_tags')->cascadeOnDelete();
            
            $table->primary(['document_id', 'tag_id']);
        });

        // Document versions
        Schema::create('document_versions', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->integer('version_number');
            $table->string('file_path', 500);
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('change_notes')->nullable();
            
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->timestamp('created_at')->useCurrent();
            
            $table->unique(['document_id', 'version_number']);
        });

        // Document shares
        Schema::create('document_shares', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()
                ->constrained()
                ->cascadeOnDelete();
            $table->string('permission', 50)->default('view'); // view, edit, admin
            $table->string('share_token', 100)->nullable()->unique();
            $table->timestamp('expires_at')->nullable();
            
            $table->foreignId('shared_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->timestamps();
        });

        // Document activities
        Schema::create('document_activities', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('action', 50); // viewed, downloaded, edited, shared, deleted
            $table->string('ip_address', 45)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['document_id', 'created_at']);
        });

        // Ticket categories
        Schema::create('ticket_categories', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 255);
            $table->string('name_ar', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('color', 20)->nullable();
            $table->foreignId('default_assignee_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // Ticket priorities
        Schema::create('ticket_priorities', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->string('name', 100);
            $table->string('name_ar', 100)->nullable();
            $table->string('color', 20)->nullable();
            $table->integer('level')->default(0);
            $table->integer('response_time_hours')->nullable();
            $table->integer('resolution_time_hours')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // SLA policies
        Schema::create('ticket_sla_policies', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->string('name', 255);
            $table->string('name_ar', 255)->nullable();
            $table->text('description')->nullable();
            $table->integer('first_response_hours')->nullable();
            $table->integer('resolution_hours')->nullable();
            $table->boolean('business_hours_only')->default(true);
            $table->json('escalation_rules')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Tickets
        Schema::create('tickets', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('ticket_number', 100)->unique();
            $table->string('subject', 500);
            $table->text('description');
            $table->string('status', 50)->default('open'); // open, in_progress, pending, resolved, closed
            $table->foreignId('priority_id')->nullable()
                ->constrained('ticket_priorities')
                ->nullOnDelete();
            $table->foreignId('category_id')->nullable()
                ->constrained('ticket_categories')
                ->nullOnDelete();
            $table->foreignId('sla_policy_id')->nullable()
                ->constrained('ticket_sla_policies')
                ->nullOnDelete();
            
            // People
            $table->foreignId('customer_id')->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            // Requester (external)
            $table->string('requester_name', 255)->nullable();
            $table->string('requester_email', 255)->nullable();
            $table->string('requester_phone', 50)->nullable();
            
            // SLA tracking
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('first_response_due_at')->nullable();
            $table->timestamp('resolution_due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->boolean('sla_breached')->default(false);
            
            // Ratings
            $table->unsignedTinyInteger('satisfaction_rating')->nullable();
            $table->text('satisfaction_comment')->nullable();
            
            $table->json('tags')->nullable();
            $table->json('custom_fields')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['branch_id', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index(['customer_id', 'status']);
            $table->fullText(['subject', 'description']);
        });

        // Ticket replies
        Schema::create('ticket_replies', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->text('content');
            $table->boolean('is_internal')->default(false);
            $table->boolean('is_customer_visible')->default(true);
            $table->string('reply_type', 50)->default('reply'); // reply, note, status_change
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['ticket_id', 'created_at']);
        });

        // Ticket attachments
        Schema::create('ticket_attachments', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reply_id')->nullable()
                ->constrained('ticket_replies')
                ->cascadeOnDelete();
            $table->string('file_name', 255);
            $table->string('file_path', 500);
            $table->string('file_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            
            $table->foreignId('uploaded_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_attachments');
        Schema::dropIfExists('ticket_replies');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('ticket_sla_policies');
        Schema::dropIfExists('ticket_priorities');
        Schema::dropIfExists('ticket_categories');
        Schema::dropIfExists('document_activities');
        Schema::dropIfExists('document_shares');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('document_tag');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_tags');
        Schema::dropIfExists('project_expenses');
        Schema::dropIfExists('project_time_logs');
        Schema::dropIfExists('task_dependencies');
        Schema::dropIfExists('project_tasks');
        Schema::dropIfExists('project_milestones');
        Schema::dropIfExists('projects');
    }
};
