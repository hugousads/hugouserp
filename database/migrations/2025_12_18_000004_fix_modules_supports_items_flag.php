<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * BUG FIX: Ensure all modules that should support items have the flag set properly.
     * This fixes the issue where module dropdown is empty when adding products.
     */
    public function up(): void
    {
        // Set supports_items=true for all modules that should support products/items
        DB::table('modules')
            ->whereIn('key', [
                'inventory',
                'rental', 
                'pos', 
                'sales', 
                'purchases', 
                'spare_parts',
                'spares',
                'motorcycle',
                'wood',
                'manufacturing'
            ])
            ->update(['supports_items' => true]);

        // Also set it for any module that has inventory-related flags
        DB::table('modules')
            ->where(function ($query) {
                $query->where('has_inventory', true)
                      ->orWhere('is_rental', true);
            })
            ->update(['supports_items' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback - this is a data fix
        // The supports_items column will remain as set
    }
};
