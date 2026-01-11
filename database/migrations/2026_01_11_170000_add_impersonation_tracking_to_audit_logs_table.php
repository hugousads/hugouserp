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
        // Check for existing columns before migration
        $hasPerformedById = Schema::hasColumn('audit_logs', 'performed_by_id');
        $hasImpersonatingAsId = Schema::hasColumn('audit_logs', 'impersonating_as_id');

        // Only run migration if at least one column is missing
        if ($hasPerformedById && $hasImpersonatingAsId) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table) use ($hasPerformedById, $hasImpersonatingAsId) {
            // Add performed_by_id: The actual user who performed the action
            // This will differ from user_id when impersonation is active
            if (! $hasPerformedById) {
                $table->foreignId('performed_by_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('users')
                    ->nullOnDelete()
                    ->comment('The actual user who performed the action (impersonator)');
            }

            // Add impersonating_as_id: The user being impersonated
            if (! $hasImpersonatingAsId) {
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
        // Check if index exists before dropping
        $indexes = Schema::getIndexes('audit_logs');
        $indexNames = array_column($indexes, 'name');

        Schema::table('audit_logs', function (Blueprint $table) use ($indexNames) {
            // Drop the index first if it exists
            if (in_array('idx_audit_impersonation', $indexNames, true)) {
                $table->dropIndex('idx_audit_impersonation');
            }
        });

        // Drop columns if they exist
        if (Schema::hasColumn('audit_logs', 'impersonating_as_id')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->dropForeign(['impersonating_as_id']);
                $table->dropColumn('impersonating_as_id');
            });
        }

        if (Schema::hasColumn('audit_logs', 'performed_by_id')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->dropForeign(['performed_by_id']);
                $table->dropColumn('performed_by_id');
            });
        }
    }
};
