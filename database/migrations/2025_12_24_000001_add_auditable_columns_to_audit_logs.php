<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('audit_logs', 'auditable_type')) {
                $table->string('auditable_type')->nullable()->after('subject_id');
            }

            if (! Schema::hasColumn('audit_logs', 'auditable_id')) {
                $table->unsignedBigInteger('auditable_id')->nullable()->after('auditable_type');
            }
        });

        // Backfill the new columns from existing subject_* data to keep modules tracking consistent
        DB::table('audit_logs')
            ->whereNull('auditable_type')
            ->update([
                'auditable_type' => DB::raw('subject_type'),
            ]);

        DB::table('audit_logs')
            ->whereNull('auditable_id')
            ->update([
                'auditable_id' => DB::raw('subject_id'),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            if (Schema::hasColumn('audit_logs', 'auditable_id')) {
                $table->dropColumn('auditable_id');
            }

            if (Schema::hasColumn('audit_logs', 'auditable_type')) {
                $table->dropColumn('auditable_type');
            }
        });
    }
};
