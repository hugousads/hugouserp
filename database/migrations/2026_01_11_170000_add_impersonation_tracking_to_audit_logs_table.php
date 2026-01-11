<?php

declare(strict_types=1);

/**
 * Add impersonation tracking fields to audit_logs table.
 *
 * Security Enhancement: Track the actual performer vs impersonated user
 * to maintain proper audit trail during impersonation sessions.
 *
 * Fields added:
 * - performed_by_id: The actual user who performed the action (the impersonator)
 * - impersonating_as_id: The user being impersonated (if any)
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // Add performed_by_id: The actual user who performed the action
            // This will differ from user_id when impersonation is active
            if (! Schema::hasColumn('audit_logs', 'performed_by_id')) {
                $table->foreignId('performed_by_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('users')
                    ->nullOnDelete()
                    ->comment('The actual user who performed the action (impersonator)');
            }

            // Add impersonating_as_id: The user being impersonated
            if (! Schema::hasColumn('audit_logs', 'impersonating_as_id')) {
                $table->foreignId('impersonating_as_id')
                    ->nullable()
                    ->after('performed_by_id')
                    ->constrained('users')
                    ->nullOnDelete()
                    ->comment('The user being impersonated during this action');
            }

            // Add index for impersonation audit queries
            $table->index(['performed_by_id', 'impersonating_as_id', 'created_at'], 'idx_audit_impersonation');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // Drop the index first
            try {
                $table->dropIndex('idx_audit_impersonation');
            } catch (\Throwable $e) {
                // Index may not exist
            }

            // Drop foreign key constraints and columns
            if (Schema::hasColumn('audit_logs', 'impersonating_as_id')) {
                $table->dropForeign(['impersonating_as_id']);
                $table->dropColumn('impersonating_as_id');
            }

            if (Schema::hasColumn('audit_logs', 'performed_by_id')) {
                $table->dropForeign(['performed_by_id']);
                $table->dropColumn('performed_by_id');
            }
        });
    }
};
