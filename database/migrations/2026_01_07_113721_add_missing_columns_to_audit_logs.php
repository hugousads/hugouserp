<?php

declare(strict_types=1);

/**
 * Add Missing Columns to Audit Logs
 * 
 * This migration adds columns that the application code expects but are missing
 * from the audit_logs table. The table was created with Spatie Activity Log
 * convention but the application uses custom columns.
 * 
 * Added columns:
 * - user_id: Maps to causer_id for easier queries
 * - action: Maps to event for application-specific action names
 * - module_key: For module context tracking
 * - target_user_id: For tracking actions on other users
 * - ip: Maps to ip_address for consistency with existing code
 * - old_values: For storing previous state (maps to properties)
 * - new_values: For storing new state (maps to properties)
 * - meta: For additional metadata
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                // Add user_id if it doesn't exist (maps to causer_id)
                if (!Schema::hasColumn('audit_logs', 'user_id')) {
                    $table->foreignId('user_id')->nullable()
                        ->after('causer_id')
                        ->constrained()
                        ->nullOnDelete();
                }
                
                // Add action if it doesn't exist (maps to event)
                if (!Schema::hasColumn('audit_logs', 'action')) {
                    $table->string('action', 255)->nullable()
                        ->after('event')
                        ->index();
                }
                
                // Add module_key if it doesn't exist
                if (!Schema::hasColumn('audit_logs', 'module_key')) {
                    $table->string('module_key', 100)->nullable()
                        ->after('branch_id')
                        ->index();
                }
                
                // Add target_user_id if it doesn't exist
                if (!Schema::hasColumn('audit_logs', 'target_user_id')) {
                    $table->foreignId('target_user_id')->nullable()
                        ->after('user_id')
                        ->constrained('users')
                        ->nullOnDelete();
                }
                
                // Add ip if it doesn't exist (maps to ip_address)
                if (!Schema::hasColumn('audit_logs', 'ip')) {
                    $table->string('ip', 45)->nullable()
                        ->after('ip_address');
                }
                
                // Add old_values if it doesn't exist
                if (!Schema::hasColumn('audit_logs', 'old_values')) {
                    $table->json('old_values')->nullable()
                        ->after('properties');
                }
                
                // Add new_values if it doesn't exist
                if (!Schema::hasColumn('audit_logs', 'new_values')) {
                    $table->json('new_values')->nullable()
                        ->after('old_values');
                }
                
                // Add meta if it doesn't exist
                if (!Schema::hasColumn('audit_logs', 'meta')) {
                    $table->json('meta')->nullable()
                        ->after('new_values');
                }
            });
            
            // Sync existing data from Spatie columns to custom columns
            DB::statement('UPDATE audit_logs SET user_id = causer_id WHERE user_id IS NULL AND causer_id IS NOT NULL');
            DB::statement('UPDATE audit_logs SET action = event WHERE action IS NULL AND event IS NOT NULL');
            DB::statement('UPDATE audit_logs SET ip = ip_address WHERE ip IS NULL AND ip_address IS NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                // Drop added columns
                $columnsToCheck = [
                    'meta',
                    'new_values',
                    'old_values',
                    'ip',
                    'target_user_id',
                    'module_key',
                    'action',
                    'user_id',
                ];
                
                foreach ($columnsToCheck as $column) {
                    if (Schema::hasColumn('audit_logs', $column)) {
                        // Drop foreign keys first if they exist
                        if (in_array($column, ['user_id', 'target_user_id'])) {
                            try {
                                $table->dropForeign(['audit_logs_' . $column . '_foreign']);
                            } catch (\Throwable $e) {
                                // Foreign key may not exist
                            }
                        }
                        
                        // Drop indexes if they exist
                        if (in_array($column, ['action', 'module_key'])) {
                            try {
                                $table->dropIndex(['audit_logs_' . $column . '_index']);
                            } catch (\Throwable $e) {
                                // Index may not exist
                            }
                        }
                        
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
