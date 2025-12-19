<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve workflow history when branches or approvals are removed
        Schema::table('workflow_instances', function (Blueprint $table) {
            if (Schema::hasColumn('workflow_instances', 'branch_id')) {
                $table->dropForeign(['branch_id']);
                $table->foreign('branch_id')
                    ->references('id')
                    ->on('branches')
                    ->nullOnDelete();
            }
        });

        Schema::table('workflow_notifications', function (Blueprint $table) {
            if (Schema::hasColumn('workflow_notifications', 'workflow_approval_id')) {
                $table->dropForeign(['workflow_approval_id']);
                $table->foreign('workflow_approval_id')
                    ->references('id')
                    ->on('workflow_approvals')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('workflow_notifications', 'delivery_status')) {
                $table->string('delivery_status')->default('pending')->after('is_sent');
            }

            if (! Schema::hasColumn('workflow_notifications', 'priority')) {
                $table->string('priority')->default('normal')->after('delivery_status');
            }

            if (! Schema::hasColumn('workflow_notifications', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('priority');
            }

            if (! Schema::hasColumn('workflow_notifications', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('delivered_at');
            }

            if (! Schema::hasColumn('workflow_notifications', 'metadata')) {
                $table->json('metadata')->nullable()->after('message');
            }
        });
    }

    public function down(): void
    {
        Schema::table('workflow_notifications', function (Blueprint $table) {
            if (Schema::hasColumn('workflow_notifications', 'workflow_approval_id')) {
                $table->dropForeign(['workflow_approval_id']);
                $table->foreign('workflow_approval_id')
                    ->references('id')
                    ->on('workflow_approvals')
                    ->onDelete('cascade');
            }

            foreach (['metadata', 'read_at', 'delivered_at', 'priority', 'delivery_status'] as $column) {
                if (Schema::hasColumn('workflow_notifications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('workflow_instances', function (Blueprint $table) {
            if (Schema::hasColumn('workflow_instances', 'branch_id')) {
                $table->dropForeign(['branch_id']);
                $table->foreign('branch_id')
                    ->references('id')
                    ->on('branches')
                    ->onDelete('cascade');
            }
        });
    }
};
