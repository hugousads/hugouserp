<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Roles table
         */
        Schema::create('roles', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->string('name');
            // Default 'web' matches config('auth.defaults.guard')
            $table->string('guard_name')->default('web');
            $table->timestamps();

            $table->index('created_at');
            $table->index('updated_at');
            $table->unique(['name', 'guard_name'], 'roles_name_guard_name_unique');
        });

        /**
         * Permissions table
         */
        Schema::create('permissions', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->string('name');
            // Default 'web' matches config('auth.defaults.guard')
            $table->string('guard_name')->default('web');
            $table->timestamps();

            $table->index('created_at');
            $table->index('updated_at');
            $table->unique(['name', 'guard_name'], 'permissions_name_guard_name_unique');
        });

        /**
         * model_has_roles
         * (ربط الموديلات بالأدوار)
         */
        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->unsignedBigInteger('role_id');

            // بدل morphs() عشان نقدر نتحكم ونضيف index باسم واضح
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            // index شبيه باللي في spatie/permission
            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->onDelete('cascade');

            $table->primary(
                ['role_id', 'model_id', 'model_type'],
                'model_has_roles_role_model_type_primary'
            );
        });

        /**
         * model_has_permissions
         * (ربط الموديلات بالصلاحيات مباشرة)
         */
        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->unsignedBigInteger('permission_id');

            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');

            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->onDelete('cascade');

            $table->primary(
                ['permission_id', 'model_id', 'model_type'],
                'model_has_permissions_permission_model_type_primary'
            );
        });

        /**
         * role_has_permissions
         * (ربط الأدوار بالصلاحيات)
         */
        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('permission_id');

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->onDelete('cascade');

            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->onDelete('cascade');

            $table->primary(
                ['role_id', 'permission_id'],
                'role_has_permissions_role_permission_primary'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
