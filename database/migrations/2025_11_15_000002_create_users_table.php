<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id')->comment('id');
            $table->string('name')->comment('name');
            $table->string('email')->unique()->comment('email');
            $table->timestamp('email_verified_at')->nullable()->comment('email_verified_at');
            $table->string('password')->comment('password');
            $table->string('phone')->nullable()->comment('phone');
            $table->boolean('is_active')->default(true)->comment('is_active');
            $table->string('username')->nullable()->unique()->comment('username');
            $table->string('avatar')->nullable()->comment('avatar');
            $table->string('locale', 10)->nullable()->comment('locale');
            $table->string('timezone')->nullable()->comment('timezone');
            $table->foreignId('branch_id')
                ->nullable()
                ->constrained('branches')
                ->nullOnDelete(); // primaryBranch
            $table->rememberToken();
            $table->timestamp('last_login_at')->nullable()->comment('last_login_at');
            $table->decimal('max_discount_percent', 5, 2)->nullable()->comment('max_discount_percent');
            $table->decimal('daily_discount_limit', 12, 2)->nullable()->comment('daily_discount_limit');
            $table->boolean('can_modify_price')->default(true)->comment('can_modify_price');
            $table->unsignedInteger('max_sessions')->default(3)->comment('max_sessions');
            $table->timestamps();
            $table->index('created_at');
            $table->index('updated_at');
            $table->softDeletes();
            $table->index('deleted_at');

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
