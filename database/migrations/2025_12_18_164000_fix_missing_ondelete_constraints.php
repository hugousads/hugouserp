<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration fixes foreign keys that are missing onDelete behavior.
     * Without explicit onDelete, Laravel defaults to RESTRICT which prevents
     * parent record deletion if children exist. This can cause operational issues.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        
        if ($driver === 'mysql') {
            // Fix: journal_entries.branch_id - should cascade when branch is deleted
            if (Schema::hasTable('journal_entries') && Schema::hasColumn('journal_entries', 'branch_id')) {
                $this->dropForeignKeyIfExists('journal_entries', 'journal_entries_branch_id_foreign');
                
                Schema::table('journal_entries', function (Blueprint $table) {
                    $table->foreign('branch_id')
                        ->references('id')
                        ->on('branches')
                        ->onDelete('cascade');
                });
            }
            
            // Fix: journal_entry_lines.account_id - should prevent deletion if account has lines (RESTRICT is correct)
            // No change needed - RESTRICT is appropriate for accounting data integrity
            
            // Fix: production_orders.bom_id - should restrict deletion of BOM if orders exist
            // No change needed - RESTRICT is appropriate
            
            // Fix: production_orders.product_id - should restrict deletion of product if orders exist
            // No change needed - RESTRICT is appropriate
            
            // Fix: production_orders.warehouse_id - should restrict deletion of warehouse if orders exist
            // No change needed - RESTRICT is appropriate
            
            // Fix: production_order_items.product_id - should restrict deletion of product if in use
            // No change needed - RESTRICT is appropriate
        }
    }

    /**
     * Helper method to drop foreign key if it exists
     */
    protected function dropForeignKeyIfExists(string $table, string $foreignKey): void
    {
        try {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $foreignKeys = $sm->listTableForeignKeys($table);
            
            foreach ($foreignKeys as $fk) {
                if ($fk->getName() === $foreignKey) {
                    Schema::table($table, function (Blueprint $blueprint) use ($foreignKey) {
                        $blueprint->dropForeign($foreignKey);
                    });
                    break;
                }
            }
        } catch (\Exception $e) {
            // Foreign key might not exist or other error - continue
            Log::warning("Could not drop foreign key {$foreignKey} on table {$table}: " . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: We don't revert as these are corrections to improve data integrity
    }
};
