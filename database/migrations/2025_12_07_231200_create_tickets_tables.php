<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ticket SLA policies table - Create first (no dependencies)
        Schema::create('ticket_sla_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('first_response_time_hours');
            $table->integer('resolution_time_hours');
            $table->json('business_hours')->nullable(); // {"monday": {"start": "09:00", "end": "17:00"}, ...}
            $table->boolean('exclude_weekends')->default(false);
            $table->json('excluded_dates')->nullable(); // Array of holiday dates
            $table->boolean('auto_escalate')->default(false);
            $table->foreignId('escalate_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Ticket priorities table - Create second (no dependencies)
        Schema::create('ticket_priorities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color', 7)->default('#3B82F6');
            $table->integer('level')->unique(); // 1=lowest, higher=more urgent
            $table->integer('response_time_hours')->default(24);
            $table->integer('resolution_time_hours')->default(72);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Ticket categories table - Create third (depends on ticket_sla_policies)
        Schema::create('ticket_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('ticket_categories')->nullOnDelete();
            $table->foreignId('default_assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sla_policy_id')->nullable()->constrained('ticket_sla_policies')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->index('parent_id');
        });

        // Tickets table
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique();
            $table->string('subject');
            $table->text('description');
            $table->enum('status', ['new', 'open', 'pending', 'resolved', 'closed'])->default('new');
            $table->foreignId('priority_id')->constrained('ticket_priorities');
            $table->foreignId('category_id')->constrained('ticket_categories');
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sla_policy_id')->nullable()->constrained('ticket_sla_policies')->nullOnDelete();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('sla_due_at')->nullable();
            $table->boolean('is_overdue')->default(false);
            $table->text('resolution_notes')->nullable();
            $table->decimal('satisfaction_rating', 3, 2)->nullable(); // 1.00 to 5.00
            $table->text('satisfaction_comment')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
            $table->index('priority_id');
            $table->index('category_id');
            $table->index('customer_id');
            $table->index('assigned_to');
            $table->index('is_overdue');
            $table->index('created_at');
        });

        // Ticket replies table
        Schema::create('ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->text('message');
            $table->boolean('is_internal')->default(false); // Internal notes vs customer-facing replies
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_email')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index(['ticket_id', 'created_at']);
            $table->index('user_id');
        });

        // Ticket attachments table
        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('reply_id')->nullable()->constrained('ticket_replies')->nullOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type', 50);
            $table->unsignedBigInteger('file_size');
            $table->string('mime_type');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');

            $table->index('ticket_id');
            $table->index('reply_id');
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
    }
};
