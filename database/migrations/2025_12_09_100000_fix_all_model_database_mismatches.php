<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration fixes all mismatches between Models and Database Schema
     * to achieve 100% compatibility.
     */
    public function up(): void
    {
        // 1. Documents table - Add version and is_public columns
        if (Schema::hasTable('documents')) {
            Schema::table('documents', function (Blueprint $table) {
                if (!Schema::hasColumn('documents', 'version')) {
                    $table->integer('version')->default(1)->after('mime_type');
                }
                if (!Schema::hasColumn('documents', 'is_public')) {
                    $table->boolean('is_public')->default(false)->after('mime_type');
                }
            });
        }

        // 2. Document Versions table - Add file_name, mime_type, and change_notes
        if (Schema::hasTable('document_versions')) {
            Schema::table('document_versions', function (Blueprint $table) {
                if (!Schema::hasColumn('document_versions', 'file_name')) {
                    $table->string('file_name')->nullable()->after('version_number');
                }
                if (!Schema::hasColumn('document_versions', 'mime_type')) {
                    $table->string('mime_type')->nullable()->after('file_size');
                }
                if (!Schema::hasColumn('document_versions', 'change_notes')) {
                    $table->text('change_notes')->nullable()->after('uploaded_by');
                }
            });
        }

        // 3. Document Activities table - Add details column
        if (Schema::hasTable('document_activities')) {
            Schema::table('document_activities', function (Blueprint $table) {
                if (!Schema::hasColumn('document_activities', 'details')) {
                    $table->json('details')->nullable()->after('action');
                }
            });
        }

        // 4. Document Shares table - Add user_id with foreign key
        if (Schema::hasTable('document_shares')) {
            Schema::table('document_shares', function (Blueprint $table) {
                if (!Schema::hasColumn('document_shares', 'user_id')) {
                    $table->unsignedBigInteger('user_id')->nullable()->after('document_id');
                    $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                }
            });
        }

        // 5. Tickets table - Add priority string, tags, and due_date
        if (Schema::hasTable('tickets')) {
            Schema::table('tickets', function (Blueprint $table) {
                if (!Schema::hasColumn('tickets', 'priority')) {
                    $table->string('priority')->default('medium')->after('status');
                }
                if (!Schema::hasColumn('tickets', 'tags')) {
                    $table->json('tags')->nullable()->after('satisfaction_comment');
                }
                if (!Schema::hasColumn('tickets', 'due_date')) {
                    $table->datetime('due_date')->nullable()->after('branch_id');
                }
            });
        }

        // 6. Ticket Priorities table - Add response_time_minutes, resolution_time_minutes, sort_order
        if (Schema::hasTable('ticket_priorities')) {
            Schema::table('ticket_priorities', function (Blueprint $table) {
                if (!Schema::hasColumn('ticket_priorities', 'response_time_minutes')) {
                    $table->integer('response_time_minutes')->nullable()->after('response_time_hours');
                }
                if (!Schema::hasColumn('ticket_priorities', 'resolution_time_minutes')) {
                    $table->integer('resolution_time_minutes')->nullable()->after('resolution_time_hours');
                }
                if (!Schema::hasColumn('ticket_priorities', 'sort_order')) {
                    $table->integer('sort_order')->default(0)->after('resolution_time_minutes');
                }
            });
        }

        // 7. Ticket SLA Policies table - Add response_time_minutes, resolution_time_minutes, business_hours_only, business_hours_start, business_hours_end, working_days
        if (Schema::hasTable('ticket_sla_policies')) {
            Schema::table('ticket_sla_policies', function (Blueprint $table) {
                if (!Schema::hasColumn('ticket_sla_policies', 'response_time_minutes')) {
                    $table->integer('response_time_minutes')->nullable()->after('first_response_time_hours');
                }
                if (!Schema::hasColumn('ticket_sla_policies', 'resolution_time_minutes')) {
                    $table->integer('resolution_time_minutes')->nullable()->after('resolution_time_hours');
                }
                if (!Schema::hasColumn('ticket_sla_policies', 'business_hours_only')) {
                    $table->boolean('business_hours_only')->default(false)->after('business_hours');
                }
                if (!Schema::hasColumn('ticket_sla_policies', 'business_hours_start')) {
                    $table->time('business_hours_start')->nullable()->after('business_hours_only');
                }
                if (!Schema::hasColumn('ticket_sla_policies', 'business_hours_end')) {
                    $table->time('business_hours_end')->nullable()->after('business_hours_start');
                }
                if (!Schema::hasColumn('ticket_sla_policies', 'working_days')) {
                    $table->json('working_days')->nullable()->after('business_hours_end');
                }
            });
        }

        // 8. Project Expenses table - Add currency_id, date, vendor, approved_at, is_reimbursable, reimbursed_to, reimbursed_at, updated_by
        if (Schema::hasTable('project_expenses')) {
            Schema::table('project_expenses', function (Blueprint $table) {
                if (!Schema::hasColumn('project_expenses', 'currency_id')) {
                    $table->unsignedBigInteger('currency_id')->nullable()->after('amount');
                }
                if (!Schema::hasColumn('project_expenses', 'date')) {
                    $table->date('date')->nullable()->after('expense_date');
                }
                if (!Schema::hasColumn('project_expenses', 'vendor')) {
                    $table->string('vendor')->nullable()->after('date');
                }
                if (!Schema::hasColumn('project_expenses', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable()->after('approved_date');
                }
                if (!Schema::hasColumn('project_expenses', 'is_reimbursable')) {
                    $table->boolean('is_reimbursable')->default(false)->after('approved_at');
                }
                if (!Schema::hasColumn('project_expenses', 'reimbursed_to')) {
                    $table->unsignedBigInteger('reimbursed_to')->nullable()->after('is_reimbursable');
                }
                if (!Schema::hasColumn('project_expenses', 'reimbursed_at')) {
                    $table->timestamp('reimbursed_at')->nullable()->after('reimbursed_to');
                }
                if (!Schema::hasColumn('project_expenses', 'updated_by')) {
                    $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
                }
            });
        }

        // 9. Project Time Logs table - Add employee_id, date, is_billable, notes, updated_by
        if (Schema::hasTable('project_time_logs')) {
            Schema::table('project_time_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('project_time_logs', 'employee_id')) {
                    $table->unsignedBigInteger('employee_id')->nullable()->after('user_id');
                }
                if (!Schema::hasColumn('project_time_logs', 'date')) {
                    $table->date('date')->nullable()->after('log_date');
                }
                if (!Schema::hasColumn('project_time_logs', 'is_billable')) {
                    $table->boolean('is_billable')->default(false)->after('billable');
                }
                if (!Schema::hasColumn('project_time_logs', 'notes')) {
                    $table->text('notes')->nullable()->after('description');
                }
                if (!Schema::hasColumn('project_time_logs', 'updated_by')) {
                    $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse documents table changes
        if (Schema::hasTable('documents')) {
            Schema::table('documents', function (Blueprint $table) {
                if (Schema::hasColumn('documents', 'version')) {
                    $table->dropColumn('version');
                }
                if (Schema::hasColumn('documents', 'is_public')) {
                    $table->dropColumn('is_public');
                }
            });
        }

        // Reverse document_versions table changes
        if (Schema::hasTable('document_versions')) {
            Schema::table('document_versions', function (Blueprint $table) {
                if (Schema::hasColumn('document_versions', 'file_name')) {
                    $table->dropColumn('file_name');
                }
                if (Schema::hasColumn('document_versions', 'mime_type')) {
                    $table->dropColumn('mime_type');
                }
                if (Schema::hasColumn('document_versions', 'change_notes')) {
                    $table->dropColumn('change_notes');
                }
            });
        }

        // Reverse document_activities table changes
        if (Schema::hasTable('document_activities')) {
            Schema::table('document_activities', function (Blueprint $table) {
                if (Schema::hasColumn('document_activities', 'details')) {
                    $table->dropColumn('details');
                }
            });
        }

        // Reverse document_shares table changes
        if (Schema::hasTable('document_shares')) {
            Schema::table('document_shares', function (Blueprint $table) {
                if (Schema::hasColumn('document_shares', 'user_id')) {
                    $table->dropForeign(['user_id']);
                    $table->dropColumn('user_id');
                }
            });
        }

        // Reverse tickets table changes
        if (Schema::hasTable('tickets')) {
            Schema::table('tickets', function (Blueprint $table) {
                if (Schema::hasColumn('tickets', 'priority')) {
                    $table->dropColumn('priority');
                }
                if (Schema::hasColumn('tickets', 'tags')) {
                    $table->dropColumn('tags');
                }
                if (Schema::hasColumn('tickets', 'due_date')) {
                    $table->dropColumn('due_date');
                }
            });
        }

        // Reverse ticket_priorities table changes
        if (Schema::hasTable('ticket_priorities')) {
            Schema::table('ticket_priorities', function (Blueprint $table) {
                if (Schema::hasColumn('ticket_priorities', 'response_time_minutes')) {
                    $table->dropColumn('response_time_minutes');
                }
                if (Schema::hasColumn('ticket_priorities', 'resolution_time_minutes')) {
                    $table->dropColumn('resolution_time_minutes');
                }
                if (Schema::hasColumn('ticket_priorities', 'sort_order')) {
                    $table->dropColumn('sort_order');
                }
            });
        }

        // Reverse ticket_sla_policies table changes
        if (Schema::hasTable('ticket_sla_policies')) {
            Schema::table('ticket_sla_policies', function (Blueprint $table) {
                if (Schema::hasColumn('ticket_sla_policies', 'response_time_minutes')) {
                    $table->dropColumn('response_time_minutes');
                }
                if (Schema::hasColumn('ticket_sla_policies', 'resolution_time_minutes')) {
                    $table->dropColumn('resolution_time_minutes');
                }
                if (Schema::hasColumn('ticket_sla_policies', 'business_hours_only')) {
                    $table->dropColumn('business_hours_only');
                }
                if (Schema::hasColumn('ticket_sla_policies', 'business_hours_start')) {
                    $table->dropColumn('business_hours_start');
                }
                if (Schema::hasColumn('ticket_sla_policies', 'business_hours_end')) {
                    $table->dropColumn('business_hours_end');
                }
                if (Schema::hasColumn('ticket_sla_policies', 'working_days')) {
                    $table->dropColumn('working_days');
                }
            });
        }

        // Reverse project_expenses table changes
        if (Schema::hasTable('project_expenses')) {
            Schema::table('project_expenses', function (Blueprint $table) {
                if (Schema::hasColumn('project_expenses', 'currency_id')) {
                    $table->dropColumn('currency_id');
                }
                if (Schema::hasColumn('project_expenses', 'date')) {
                    $table->dropColumn('date');
                }
                if (Schema::hasColumn('project_expenses', 'vendor')) {
                    $table->dropColumn('vendor');
                }
                if (Schema::hasColumn('project_expenses', 'approved_at')) {
                    $table->dropColumn('approved_at');
                }
                if (Schema::hasColumn('project_expenses', 'is_reimbursable')) {
                    $table->dropColumn('is_reimbursable');
                }
                if (Schema::hasColumn('project_expenses', 'reimbursed_to')) {
                    $table->dropColumn('reimbursed_to');
                }
                if (Schema::hasColumn('project_expenses', 'reimbursed_at')) {
                    $table->dropColumn('reimbursed_at');
                }
                if (Schema::hasColumn('project_expenses', 'updated_by')) {
                    $table->dropColumn('updated_by');
                }
            });
        }

        // Reverse project_time_logs table changes
        if (Schema::hasTable('project_time_logs')) {
            Schema::table('project_time_logs', function (Blueprint $table) {
                if (Schema::hasColumn('project_time_logs', 'employee_id')) {
                    $table->dropColumn('employee_id');
                }
                if (Schema::hasColumn('project_time_logs', 'date')) {
                    $table->dropColumn('date');
                }
                if (Schema::hasColumn('project_time_logs', 'is_billable')) {
                    $table->dropColumn('is_billable');
                }
                if (Schema::hasColumn('project_time_logs', 'notes')) {
                    $table->dropColumn('notes');
                }
                if (Schema::hasColumn('project_time_logs', 'updated_by')) {
                    $table->dropColumn('updated_by');
                }
            });
        }
    }
};
