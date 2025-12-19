<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Documents table
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type', 50);
            $table->unsignedBigInteger('file_size'); // in bytes
            $table->string('mime_type');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->enum('access_level', ['public', 'private', 'shared'])->default('private');
            $table->string('category')->nullable();
            $table->string('folder')->nullable();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->integer('version_number')->default(1);
            $table->integer('download_count')->default(0);
            $table->json('metadata')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
            $table->index('category');
            $table->index('folder');
            $table->index('file_type');
            $table->index('uploaded_by');
        });

        // Document versions table
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->integer('version_number');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size');
            $table->text('change_description')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->integer('download_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'version_number']);
            $table->unique(['document_id', 'version_number']);
        });

        // Document tags table
        Schema::create('document_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('color', 7)->default('#3B82F6'); // hex color
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Document tag pivot table
        Schema::create('document_tag', function (Blueprint $table) {
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('document_tag_id')->constrained('document_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['document_id', 'document_tag_id']);
        });

        // Document shares table
        Schema::create('document_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('shared_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shared_with_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('shared_with_role')->nullable();
            $table->enum('permission', ['view', 'download', 'edit', 'manage'])->default('view');
            $table->date('expires_at')->nullable();
            $table->string('password_hash')->nullable();
            $table->boolean('notify_on_access')->default(false);
            $table->integer('access_count')->default(0);
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'shared_with_user_id']);
            $table->index('shared_with_role');
        });

        // Document activities table
        Schema::create('document_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('action', ['created', 'viewed', 'downloaded', 'edited', 'shared', 'deleted', 'restored', 'version_created']);
            $table->text('description')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index(['document_id', 'created_at']);
            $table->index('user_id');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_activities');
        Schema::dropIfExists('document_shares');
        Schema::dropIfExists('document_tag');
        Schema::dropIfExists('document_tags');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
    }
};
