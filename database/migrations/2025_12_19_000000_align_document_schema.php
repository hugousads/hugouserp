<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DOCUMENT_ACTIONS = ['created', 'viewed', 'downloaded', 'edited', 'shared', 'unshared', 'deleted', 'restored', 'version_created'];

    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (!Schema::hasColumn('documents', 'is_public')) {
                $table->boolean('is_public')->default(false)->after('access_level');
            }

            if (!Schema::hasColumn('documents', 'version')) {
                $table->integer('version')->default(1)->after('version_number');
            }
        });

        Schema::table('document_versions', function (Blueprint $table) {
            if (!Schema::hasColumn('document_versions', 'file_name')) {
                $table->string('file_name')->after('version_number');
            }

            if (!Schema::hasColumn('document_versions', 'mime_type')) {
                $table->string('mime_type')->nullable()->after('file_size');
            }

            if (!Schema::hasColumn('document_versions', 'change_notes')) {
                $table->text('change_notes')->nullable()->after('change_description');
            }
        });

        Schema::table('document_shares', function (Blueprint $table) {
            if (!Schema::hasColumn('document_shares', 'user_id')) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('shared_by')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        DB::statement('UPDATE document_shares SET user_id = shared_with_user_id WHERE user_id IS NULL AND shared_with_user_id IS NOT NULL');

        if (Schema::hasTable('document_activities')) {
            $pdo = Schema::getConnection()->getPdo();
            $actionEnumList = implode(',', array_map(fn ($value) => $pdo->quote($value), self::DOCUMENT_ACTIONS));
            $driver = Schema::getConnection()->getDriverName();
            if (in_array($driver, ['mysql', 'mariadb'])) {
                DB::statement("ALTER TABLE document_activities MODIFY action ENUM({$actionEnumList}) NOT NULL");
            } elseif ($driver === 'sqlite') {
                // SQLite stores enums as TEXT without strict constraints, so the base migration already permits the new value.
            } else {
                logger()->warning('Document activities action enum not automatically altered for this driver', ['driver' => $driver]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('document_shares', function (Blueprint $table) {
            if (Schema::hasColumn('document_shares', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
        });

        Schema::table('document_versions', function (Blueprint $table) {
            if (Schema::hasColumn('document_versions', 'change_notes')) {
                $table->dropColumn('change_notes');
            }

            if (Schema::hasColumn('document_versions', 'mime_type')) {
                $table->dropColumn('mime_type');
            }

            if (Schema::hasColumn('document_versions', 'file_name')) {
                $table->dropColumn('file_name');
            }
        });

        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'version')) {
                $table->dropColumn('version');
            }

            if (Schema::hasColumn('documents', 'is_public')) {
                $table->dropColumn('is_public');
            }
        });
    }
};
