<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Merge duplicate motorcycles/motorcycle modules
        $motorcycles = DB::table('modules')->where('key', 'motorcycles')->first();
        $motorcycle = DB::table('modules')->where('key', 'motorcycle')->first();
        
        if ($motorcycles && $motorcycle) {
            // Update branch_modules references from motorcycles to motorcycle
            DB::table('branch_modules')
                ->where('module_key', 'motorcycles')
                ->update(['module_key' => 'motorcycle', 'module_id' => $motorcycle->id]);
            
            // Delete the duplicate motorcycles entry
            DB::table('modules')->where('key', 'motorcycles')->delete();
        }
        
        // Merge duplicate spare_parts/spares modules
        $spareParts = DB::table('modules')->where('key', 'spare_parts')->first();
        $spares = DB::table('modules')->where('key', 'spares')->first();
        
        if ($spareParts && $spares) {
            // Update branch_modules references from spare_parts to spares
            DB::table('branch_modules')
                ->where('module_key', 'spare_parts')
                ->update(['module_key' => 'spares', 'module_id' => $spares->id]);
            
            // Delete the duplicate spare_parts entry
            DB::table('modules')->where('key', 'spare_parts')->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is not reversible as it deletes duplicate data
        // If needed, the duplicates would need to be manually recreated
    }
};
