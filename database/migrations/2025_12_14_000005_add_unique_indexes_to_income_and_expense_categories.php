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
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->unique(['branch_id', 'name'], 'expense_categories_branch_name_unique');
        });

        Schema::table('income_categories', function (Blueprint $table) {
            $table->unique(['branch_id', 'name'], 'income_categories_branch_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropUnique('expense_categories_branch_name_unique');
        });

        Schema::table('income_categories', function (Blueprint $table) {
            $table->dropUnique('income_categories_branch_name_unique');
        });
    }
};
