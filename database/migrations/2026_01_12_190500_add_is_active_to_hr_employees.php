<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add is_active boolean column to hr_employees table
     * This column will be synchronized with the status column
     * - is_active = true when status = 'active'
     * - is_active = false when status != 'active'
     */
    public function up(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            // Add is_active column with default true
            $table->boolean('is_active')->default(true)->after('status');
            
            // Add index for better query performance
            $table->index(['branch_id', 'is_active']);
        });

        // Sync is_active with existing status values
        DB::table('hr_employees')
            ->where('status', 'active')
            ->update(['is_active' => true]);

        DB::table('hr_employees')
            ->where('status', '!=', 'active')
            ->update(['is_active' => false]);
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'is_active']);
            $table->dropColumn('is_active');
        });
    }
};
