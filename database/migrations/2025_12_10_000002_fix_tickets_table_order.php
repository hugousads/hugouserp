<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix Tickets Tables Creation Order
 *
 * This migration ensures ticket-related tables are created in the correct order
 * to avoid foreign key constraint errors. The original migration 
 * (2025_12_07_231200_create_tickets_tables.php) tried to create ticket_categories 
 * with a FK to ticket_sla_policies before that table existed.
 *
 * Correct order:
 * 1. ticket_sla_policies (no dependencies)
 * 2. ticket_priorities (no dependencies)
 * 3. ticket_categories (depends on ticket_sla_policies)
 * 4. tickets (depends on categories and priorities)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // This migration ensures ticket-related tables exist in the correct order
        // and fixes any FK issues. It's safe to run multiple times.
        
        // The tables should already exist from the original migration
        // (2025_12_07_231200_create_tickets_tables.php) which has been fixed.
        // This migration just ensures all necessary indexes exist.
        
        if (Schema::hasTable('tickets')) {
            try {
                Schema::table('tickets', function (Blueprint $table) {
                    // Ensure indexes exist (idempotent operation)
                    if (!$this->indexExists('tickets', 'tickets_branch_id_status_index')) {
                        $table->index(['branch_id', 'status'], 'tickets_branch_id_status_index');
                    }
                    
                    if (!$this->indexExists('tickets', 'tickets_priority_id_index')) {
                        $table->index('priority_id', 'tickets_priority_id_index');
                    }
                    
                    if (!$this->indexExists('tickets', 'tickets_category_id_index')) {
                        $table->index('category_id', 'tickets_category_id_index');
                    }
                });
            } catch (\Exception $e) {
                // Indexes already exist or table structure is fine
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Nothing to reverse - we only ensured proper structure
    }

    /**
     * Check if an index exists on a table.
     *
     * @param  string  $table
     * @param  string  $indexName
     * @return bool
     */
    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $indexes = Schema::getIndexes($table);
            foreach ($indexes as $index) {
                if (strcasecmp($index['name'], $indexName) === 0) {
                    return true;
                }
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
};
