<?php

declare(strict_types=1);

/**
 * Consolidated Core Tables Migration
 * 
 * MySQL 8.4 Optimized:
 * - Uses InnoDB engine with proper character set (utf8mb4_0900_ai_ci)
 * - Optimized index strategies
 * - Proper foreign key constraints
 * - JSON column support for flexible settings
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MySQL 8.4 optimized table options
     */
    private function setTableOptions(Blueprint $table): void
    {
        $table->engine = 'InnoDB';
        $table->charset = 'utf8mb4';
        $table->collation = 'utf8mb4_0900_ai_ci'; // MySQL 8.0+ optimized collation
    }

    public function up(): void
    {
        // Cache tables (Laravel default)
        Schema::create('cache', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->string('key', 255)->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->string('key', 255)->primary();
            $table->string('owner', 255);
            $table->integer('expiration');
        });

        // Jobs tables
        Schema::create('jobs', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->string('queue', 255)->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->string('id', 255)->primary();
            $table->string('name', 255);
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->string('uuid', 255)->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        // Sessions table
        Schema::create('sessions', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->string('id', 255)->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // Password reset tokens
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->string('email', 255)->primary();
            $table->string('token', 255);
            $table->timestamp('created_at')->nullable();
        });

        // Branches - Core organizational unit
        Schema::create('branches', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->string('name', 255);
            $table->string('name_ar', 255)->nullable();
            $table->string('code', 50)->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->string('address', 500)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('timezone', 100)->nullable();
            $table->string('currency', 3)->default('EGP');
            $table->boolean('is_main')->default(false)->index();
            $table->foreignId('parent_id')->nullable()
                ->constrained('branches')
                ->nullOnDelete();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Composite indexes for common queries
            $table->index(['is_active', 'is_main']);
            $table->index(['created_at']);
        });

        // Users table
        Schema::create('users', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->string('name', 255);
            $table->string('email', 255)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password', 255);
            $table->string('phone', 50)->nullable();
            $table->string('avatar', 500)->nullable();
            $table->foreignId('branch_id')->nullable()
                ->constrained('branches')
                ->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->string('locale', 10)->default('ar');
            $table->string('timezone', 100)->nullable();
            $table->json('preferences')->nullable();
            $table->rememberToken();
            
            // 2FA fields
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('two_factor_secret', 255)->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            
            // Security fields
            $table->timestamp('password_changed_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->integer('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['is_active', 'branch_id']);
            $table->index(['email', 'is_active']);
        });

        // Branch-User pivot
        Schema::create('branch_user', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            
            $table->unique(['branch_id', 'user_id']);
        });

        // User sessions tracking
        Schema::create('user_sessions', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('session_id', 255)->unique();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('device_type', 50)->nullable();
            $table->string('browser', 100)->nullable();
            $table->string('platform', 100)->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'last_activity_at']);
        });

        // Login activities
        Schema::create('login_activities', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('status', 50); // success, failed, blocked
            $table->string('failure_reason', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['user_id', 'created_at']);
            $table->index(['email', 'status', 'created_at']);
            $table->index(['ip_address', 'created_at']);
        });

        // System settings
        Schema::create('system_settings', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->string('key', 255)->unique();
            $table->text('value')->nullable();
            $table->string('group', 100)->nullable()->index();
            $table->boolean('is_public')->default(false);
            $table->boolean('is_encrypted')->default(false);
            $table->string('type', 50)->default('string');
            $table->timestamps();
            
            $table->index(['group', 'key']);
        });

        // Currencies
        Schema::create('currencies', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name', 100);
            $table->string('name_ar', 100)->nullable();
            $table->string('symbol', 10);
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->boolean('is_base')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Currency rates
        Schema::create('currency_rates', function (Blueprint $table) {
            $this->setTableOptions($table);
            $table->id();
            $table->foreignId('currency_id')->constrained()->cascadeOnDelete();
            $table->decimal('rate', 18, 8);
            $table->date('effective_date')->index();
            $table->string('source', 100)->nullable();
            $table->timestamps();
            
            $table->unique(['currency_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_rates');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('login_activities');
        Schema::dropIfExists('user_sessions');
        Schema::dropIfExists('branch_user');
        Schema::dropIfExists('users');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
    }
};
