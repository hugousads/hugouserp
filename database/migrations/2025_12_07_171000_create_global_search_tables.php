<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Search index for fast lookups
        Schema::create('search_index', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('searchable_type'); // Model class
            $table->unsignedBigInteger('searchable_id'); // Model ID
            $table->string('title');
            $table->text('content')->nullable(); // Searchable text
            $table->string('module'); // sales, inventory, customers, etc.
            $table->string('icon')->nullable();
            $table->string('url')->nullable(); // Direct link
            $table->json('metadata')->nullable(); // Additional data
            $table->timestamp('indexed_at');

            $table->index(['branch_id', 'searchable_type']);
            $table->index('module');
            $table->index('title'); // Index on title only; fulltext covers title+content
            $table->unique(['searchable_type', 'searchable_id', 'branch_id'], 'search_index_unique');
        });

        // Add fulltext index if database supports it (MySQL/PostgreSQL)
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if (in_array($driver, ['mysql', 'pgsql'])) {
            Schema::table('search_index', function (Blueprint $table) {
                $table->fullText(['title', 'content']);
            });
        }

        // Search history for user
        Schema::create('search_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('query');
            $table->string('module')->nullable(); // If filtered by module
            $table->integer('results_count')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_history');
        Schema::dropIfExists('search_index');
    }
};
