<?php

declare(strict_types=1);

/**
 * Fix Workflow Tables Schema Mismatches
 * 
 * This migration fixes critical schema mismatches between workflow migrations
 * and their corresponding Eloquent models. The changes ensure that all model
 * fillable fields and casts have corresponding database columns.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fix workflow_definitions table
        Schema::table('workflow_definitions', function (Blueprint $table) {
            // Make old columns nullable so they don't conflict
            $table->string('trigger_event', 100)->nullable()->change();
            
            // Add missing columns
            $table->string('code', 100)->nullable()->after('name');
            $table->string('module_name', 100)->nullable()->after('code');
            $table->text('description')->nullable()->after('entity_type');
            $table->boolean('is_mandatory')->default(false)->after('is_active');
            
            // Add new columns with proper names (to avoid rename issues in SQLite)
            $table->json('stages')->nullable()->after('trigger_event');
            $table->json('rules')->nullable()->after('stages');
        });

        // Copy data from old columns to new ones for workflow_definitions
        if (Schema::hasColumn('workflow_definitions', 'steps')) {
            DB::statement('UPDATE workflow_definitions SET stages = steps WHERE steps IS NOT NULL');
        }
        if (Schema::hasColumn('workflow_definitions', 'conditions')) {
            DB::statement('UPDATE workflow_definitions SET rules = conditions WHERE conditions IS NOT NULL');
        }

        // Fix workflow_instances table
        Schema::table('workflow_instances', function (Blueprint $table) {
            // Make old foreign key nullable
            $table->foreignId('workflow_id')->nullable()->change();
            
            // Add new column with proper name
            $table->foreignId('workflow_definition_id')->nullable()->after('id')->constrained('workflow_definitions')->cascadeOnDelete();
            
            // Add branch_id column
            $table->foreignId('branch_id')->nullable()->after('workflow_definition_id')->constrained()->nullOnDelete();
            
            // Add new columns with proper names
            $table->string('current_stage', 50)->nullable()->after('entity_id');
            $table->json('metadata')->nullable()->after('status');
            
            // Add missing columns
            $table->foreignId('initiated_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('initiated_at')->nullable()->after('initiated_by');
        });

        // Copy data from old columns to new ones for workflow_instances
        if (Schema::hasColumn('workflow_instances', 'workflow_id')) {
            DB::statement('UPDATE workflow_instances SET workflow_definition_id = workflow_id WHERE workflow_id IS NOT NULL');
        }
        if (Schema::hasColumn('workflow_instances', 'current_step')) {
            DB::statement('UPDATE workflow_instances SET current_stage = current_step WHERE current_step IS NOT NULL');
        }
        if (Schema::hasColumn('workflow_instances', 'context')) {
            DB::statement('UPDATE workflow_instances SET metadata = context WHERE context IS NOT NULL');
        }

        // Fix workflow_approvals table
        Schema::table('workflow_approvals', function (Blueprint $table) {
            // Make old foreign key and step_number nullable
            $table->foreignId('instance_id')->nullable()->change();
            $table->integer('step_number')->nullable()->change();
            
            // Add new column with proper name
            $table->foreignId('workflow_instance_id')->nullable()->after('id')->constrained('workflow_instances')->cascadeOnDelete();
            
            // Add missing columns
            $table->string('stage_name', 100)->nullable()->after('workflow_instance_id');
            $table->integer('stage_order')->default(0)->after('stage_name');
            $table->string('approver_role', 100)->nullable()->after('approver_id');
            
            // Add new columns
            $table->timestamp('requested_at')->nullable()->after('status');
            $table->timestamp('responded_at')->nullable()->after('requested_at');
            
            // Add additional_data column
            $table->json('additional_data')->nullable()->after('comments');
        });

        // Copy data from old columns to new ones for workflow_approvals
        if (Schema::hasColumn('workflow_approvals', 'instance_id')) {
            DB::statement('UPDATE workflow_approvals SET workflow_instance_id = instance_id WHERE instance_id IS NOT NULL');
        }
        if (Schema::hasColumn('workflow_approvals', 'decided_at')) {
            DB::statement('UPDATE workflow_approvals SET responded_at = decided_at WHERE decided_at IS NOT NULL');
        }

        // Fix workflow_notifications table
        Schema::table('workflow_notifications', function (Blueprint $table) {
            // Make old foreign key nullable
            $table->foreignId('instance_id')->nullable()->change();
            
            // Add new column with proper name
            $table->foreignId('workflow_instance_id')->nullable()->after('id')->constrained('workflow_instances')->cascadeOnDelete();
            
            // Add missing columns
            $table->foreignId('workflow_approval_id')->nullable()->after('workflow_instance_id')->constrained()->nullOnDelete();
            $table->string('channel', 50)->default('database')->after('type');
            $table->json('metadata')->nullable()->after('message');
            
            // Add new status columns
            $table->boolean('is_sent')->default(false)->after('metadata');
            $table->string('delivery_status', 50)->default('pending')->after('is_sent');
            $table->string('priority', 50)->default('normal')->after('delivery_status');
            $table->timestamp('delivered_at')->nullable()->after('priority');
            $table->timestamp('sent_at')->nullable()->after('delivered_at');
        });

        // Copy data from old columns to new ones for workflow_notifications
        if (Schema::hasColumn('workflow_notifications', 'instance_id')) {
            DB::statement('UPDATE workflow_notifications SET workflow_instance_id = instance_id WHERE instance_id IS NOT NULL');
        }
        if (Schema::hasColumn('workflow_notifications', 'is_read')) {
            DB::statement('UPDATE workflow_notifications SET is_sent = is_read WHERE is_read IS NOT NULL');
        }

        // Fix workflow_audit_logs table
        Schema::table('workflow_audit_logs', function (Blueprint $table) {
            // Make old foreign key nullable
            $table->foreignId('instance_id')->nullable()->change();
            
            // Add missing updated_at timestamp that Laravel expects
            $table->timestamp('updated_at')->nullable()->after('created_at');
            
            // Add new column with proper name
            $table->foreignId('workflow_instance_id')->nullable()->after('id')->constrained('workflow_instances')->cascadeOnDelete();
            
            // Add missing stage transition columns
            $table->string('from_stage', 100)->nullable()->after('action');
            $table->string('to_stage', 100)->nullable()->after('from_stage');
            
            // Add new comments column
            $table->text('comments')->nullable()->after('to_stage');
            
            // Add metadata column
            $table->json('metadata')->nullable()->after('comments');
            
            // Add performed_at timestamp
            $table->timestamp('performed_at')->nullable()->after('metadata');
        });

        // Copy data from old columns to new ones for workflow_audit_logs
        if (Schema::hasColumn('workflow_audit_logs', 'instance_id')) {
            DB::statement('UPDATE workflow_audit_logs SET workflow_instance_id = instance_id WHERE instance_id IS NOT NULL');
        }
        if (Schema::hasColumn('workflow_audit_logs', 'notes')) {
            DB::statement('UPDATE workflow_audit_logs SET comments = notes WHERE notes IS NOT NULL');
        }

        // Fix workflow_rules table
        Schema::table('workflow_rules', function (Blueprint $table) {
            // Make old foreign key and action_type nullable
            $table->foreignId('workflow_id')->nullable()->change();
            $table->string('action_type', 100)->nullable()->change();
            
            // Add new column with proper name
            $table->foreignId('workflow_definition_id')->nullable()->after('id')->constrained('workflow_definitions')->cascadeOnDelete();
            
            // Add priority column
            $table->integer('priority')->default(0)->after('name');
            
            // Add missing is_active column
            $table->boolean('is_active')->default(true)->after('conditions');
            
            // Add actions column
            $table->json('actions')->nullable()->after('priority');
        });

        // Copy data from old columns to new ones for workflow_rules
        if (Schema::hasColumn('workflow_rules', 'workflow_id')) {
            DB::statement('UPDATE workflow_rules SET workflow_definition_id = workflow_id WHERE workflow_id IS NOT NULL');
        }
        if (Schema::hasColumn('workflow_rules', 'sequence')) {
            DB::statement('UPDATE workflow_rules SET priority = sequence WHERE sequence IS NOT NULL');
        }
        // Consolidate action_type and action_config into actions json
        if (Schema::hasColumn('workflow_rules', 'action_type') && Schema::hasColumn('workflow_rules', 'action_config')) {
            DB::statement("UPDATE workflow_rules SET actions = json_object('type', action_type, 'config', json(COALESCE(action_config, 'null'))) WHERE action_type IS NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: This down migration only removes the NEW columns we added.
        // It does NOT restore the old column names since those are still present.
        // In production, you should backup your database before running migrations.

        Schema::table('workflow_rules', function (Blueprint $table) {
            if (Schema::hasColumn('workflow_rules', 'actions')) {
                $table->dropColumn('actions');
            }
            if (Schema::hasColumn('workflow_rules', 'is_active')) {
                $table->dropColumn('is_active');
            }
            if (Schema::hasColumn('workflow_rules', 'priority')) {
                $table->dropColumn('priority');
            }
            if (Schema::hasColumn('workflow_rules', 'workflow_definition_id')) {
                $table->dropForeign(['workflow_definition_id']);
                $table->dropColumn('workflow_definition_id');
            }
        });

        Schema::table('workflow_audit_logs', function (Blueprint $table) {
            if (Schema::hasColumn('workflow_audit_logs', 'performed_at')) {
                $table->dropColumn('performed_at');
            }
            if (Schema::hasColumn('workflow_audit_logs', 'metadata')) {
                $table->dropColumn('metadata');
            }
            if (Schema::hasColumn('workflow_audit_logs', 'comments')) {
                $table->dropColumn('comments');
            }
            if (Schema::hasColumn('workflow_audit_logs', 'to_stage')) {
                $table->dropColumn('to_stage');
            }
            if (Schema::hasColumn('workflow_audit_logs', 'from_stage')) {
                $table->dropColumn('from_stage');
            }
            if (Schema::hasColumn('workflow_audit_logs', 'workflow_instance_id')) {
                $table->dropForeign(['workflow_instance_id']);
                $table->dropColumn('workflow_instance_id');
            }
        });

        Schema::table('workflow_notifications', function (Blueprint $table) {
            if (Schema::hasColumn('workflow_notifications', 'sent_at')) {
                $table->dropColumn('sent_at');
            }
            if (Schema::hasColumn('workflow_notifications', 'delivered_at')) {
                $table->dropColumn('delivered_at');
            }
            if (Schema::hasColumn('workflow_notifications', 'priority')) {
                $table->dropColumn('priority');
            }
            if (Schema::hasColumn('workflow_notifications', 'delivery_status')) {
                $table->dropColumn('delivery_status');
            }
            if (Schema::hasColumn('workflow_notifications', 'is_sent')) {
                $table->dropColumn('is_sent');
            }
            if (Schema::hasColumn('workflow_notifications', 'metadata')) {
                $table->dropColumn('metadata');
            }
            if (Schema::hasColumn('workflow_notifications', 'channel')) {
                $table->dropColumn('channel');
            }
            if (Schema::hasColumn('workflow_notifications', 'workflow_approval_id')) {
                $table->dropForeign(['workflow_approval_id']);
                $table->dropColumn('workflow_approval_id');
            }
            if (Schema::hasColumn('workflow_notifications', 'workflow_instance_id')) {
                $table->dropForeign(['workflow_instance_id']);
                $table->dropColumn('workflow_instance_id');
            }
        });

        Schema::table('workflow_approvals', function (Blueprint $table) {
            if (Schema::hasColumn('workflow_approvals', 'additional_data')) {
                $table->dropColumn('additional_data');
            }
            if (Schema::hasColumn('workflow_approvals', 'responded_at')) {
                $table->dropColumn('responded_at');
            }
            if (Schema::hasColumn('workflow_approvals', 'requested_at')) {
                $table->dropColumn('requested_at');
            }
            if (Schema::hasColumn('workflow_approvals', 'approver_role')) {
                $table->dropColumn('approver_role');
            }
            if (Schema::hasColumn('workflow_approvals', 'stage_order')) {
                $table->dropColumn('stage_order');
            }
            if (Schema::hasColumn('workflow_approvals', 'stage_name')) {
                $table->dropColumn('stage_name');
            }
            if (Schema::hasColumn('workflow_approvals', 'workflow_instance_id')) {
                $table->dropForeign(['workflow_instance_id']);
                $table->dropColumn('workflow_instance_id');
            }
        });

        Schema::table('workflow_instances', function (Blueprint $table) {
            if (Schema::hasColumn('workflow_instances', 'initiated_at')) {
                $table->dropColumn('initiated_at');
            }
            if (Schema::hasColumn('workflow_instances', 'initiated_by')) {
                $table->dropForeign(['initiated_by']);
                $table->dropColumn('initiated_by');
            }
            if (Schema::hasColumn('workflow_instances', 'metadata')) {
                $table->dropColumn('metadata');
            }
            if (Schema::hasColumn('workflow_instances', 'current_stage')) {
                $table->dropColumn('current_stage');
            }
            if (Schema::hasColumn('workflow_instances', 'branch_id')) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            }
            if (Schema::hasColumn('workflow_instances', 'workflow_definition_id')) {
                $table->dropForeign(['workflow_definition_id']);
                $table->dropColumn('workflow_definition_id');
            }
        });

        Schema::table('workflow_definitions', function (Blueprint $table) {
            if (Schema::hasColumn('workflow_definitions', 'rules')) {
                $table->dropColumn('rules');
            }
            if (Schema::hasColumn('workflow_definitions', 'stages')) {
                $table->dropColumn('stages');
            }
            if (Schema::hasColumn('workflow_definitions', 'is_mandatory')) {
                $table->dropColumn('is_mandatory');
            }
            if (Schema::hasColumn('workflow_definitions', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('workflow_definitions', 'module_name')) {
                $table->dropColumn('module_name');
            }
            if (Schema::hasColumn('workflow_definitions', 'code')) {
                $table->dropColumn('code');
            }
        });
    }
};
