<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration fixes SQL bugs where nullable foreign keys incorrectly use
     * CASCADE delete instead of SET NULL. This is particularly critical for:
     * 1. Self-referencing tables (parent_id, reply_id) - cascade would recursively delete children
     * 2. Optional relationships where records should survive parent deletion
     */
    public function up(): void
    {
        // Get database driver
        $driver = DB::connection()->getDriverName();
        
        if ($driver === 'mysql') {
            // Fix 1: export_layouts.report_definition_id - should set null, not cascade
            if (Schema::hasTable('export_layouts') && Schema::hasColumn('export_layouts', 'report_definition_id')) {
                $this->dropForeignKeyIfExists('export_layouts', 'export_layouts_report_definition_id_foreign');
                
                Schema::table('export_layouts', function (Blueprint $table) {
                    $table->foreign('report_definition_id')
                        ->references('id')
                        ->on('report_definitions')
                        ->onDelete('set null');
                });
            }
            
            // Fix 2: module_navigation.parent_id - self-reference should set null, not cascade
            if (Schema::hasTable('module_navigation') && Schema::hasColumn('module_navigation', 'parent_id')) {
                $this->dropForeignKeyIfExists('module_navigation', 'module_navigation_parent_id_foreign');
                
                Schema::table('module_navigation', function (Blueprint $table) {
                    $table->foreign('parent_id')
                        ->references('id')
                        ->on('module_navigation')
                        ->onDelete('set null');
                });
            }
            
            // Fix 3: project_tasks.parent_task_id - self-reference should set null, not cascade
            if (Schema::hasTable('project_tasks') && Schema::hasColumn('project_tasks', 'parent_task_id')) {
                $this->dropForeignKeyIfExists('project_tasks', 'project_tasks_parent_task_id_foreign');
                
                Schema::table('project_tasks', function (Blueprint $table) {
                    $table->foreign('parent_task_id')
                        ->references('id')
                        ->on('project_tasks')
                        ->onDelete('set null');
                });
            }
            
            // Fix 4: ticket_replies.reply_id - self-reference should set null, not cascade
            if (Schema::hasTable('ticket_replies') && Schema::hasColumn('ticket_replies', 'reply_id')) {
                $this->dropForeignKeyIfExists('ticket_replies', 'ticket_replies_reply_id_foreign');
                
                Schema::table('ticket_replies', function (Blueprint $table) {
                    $table->foreign('reply_id')
                        ->references('id')
                        ->on('ticket_replies')
                        ->onDelete('set null');
                });
            }
            
            // Fix 5: workflow_approvals.workflow_approval_id - self-reference should set null, not cascade
            if (Schema::hasTable('workflow_approvals') && Schema::hasColumn('workflow_approvals', 'workflow_approval_id')) {
                $this->dropForeignKeyIfExists('workflow_approvals', 'workflow_approvals_workflow_approval_id_foreign');
                
                Schema::table('workflow_approvals', function (Blueprint $table) {
                    $table->foreign('workflow_approval_id')
                        ->references('id')
                        ->on('workflow_approvals')
                        ->onDelete('set null');
                });
            }
            
            // Fix 6: module_navigation.module_id - should set null when module is deleted
            if (Schema::hasTable('module_navigation') && Schema::hasColumn('module_navigation', 'module_id')) {
                $this->dropForeignKeyIfExists('module_navigation', 'module_navigation_module_id_foreign');
                
                Schema::table('module_navigation', function (Blueprint $table) {
                    $table->foreign('module_id')
                        ->references('id')
                        ->on('modules')
                        ->onDelete('set null');
                });
            }
        }
    }

    /**
     * Helper method to drop foreign key if it exists
     * Compatible with Laravel 12+ (no Doctrine dependency)
     */
    protected function dropForeignKeyIfExists(string $table, string $foreignKey): void
    {
        try {
            $connection = Schema::getConnection();
            $schemaBuilder = $connection->getSchemaBuilder();
            $foreignKeys = $schemaBuilder->getForeignKeys($table);
            
            foreach ($foreignKeys as $fk) {
                if ($fk['name'] === $foreignKey) {
                    Schema::table($table, function (Blueprint $blueprint) use ($foreignKey) {
                        $blueprint->dropForeign($foreignKey);
                    });
                    break;
                }
            }
        } catch (\Exception $e) {
            // Foreign key might not exist or other error - continue
            Log::warning("Could not drop foreign key {$foreignKey} on table {$table}: " . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: We don't revert these fixes as they are bug fixes
        // The original cascade behavior was incorrect for nullable foreign keys
        // Reverting would reintroduce the bugs
    }
};
