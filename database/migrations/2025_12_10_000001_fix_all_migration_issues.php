<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Comprehensive Migration Fix
 *
 * This migration fixes all critical issues related to:
 * 1. Incorrect column names in indexes (audit_logs, suppliers, sales, rental_invoices)
 * 2. Foreign key constraints pointing to wrong tables
 * 3. Duplicate index prevention
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fix 1: audit_logs - Remove incorrect indexes and add correct ones
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                // Drop incorrect indexes if they exist
                $this->safeDropIndex('audit_logs', 'audit_logs_auditable_idx');
                $this->safeDropIndex('audit_logs', 'audit_logs_event_idx');
                $this->safeDropIndex('audit_logs', 'audit_logs_auditable_index');
                
                // Add correct indexes on actual columns
                if (Schema::hasColumn('audit_logs', 'subject_type') && 
                    Schema::hasColumn('audit_logs', 'subject_id')) {
                    $this->safeAddIndex($table, ['subject_type', 'subject_id'], 'audit_logs_subject_idx', 'audit_logs');
                }
                
                if (Schema::hasColumn('audit_logs', 'action')) {
                    $this->safeAddIndex($table, 'action', 'audit_logs_action_idx', 'audit_logs');
                }
            });
        }

        // Fix 2: suppliers - Remove status index and add is_active index
        if (Schema::hasTable('suppliers')) {
            Schema::table('suppliers', function (Blueprint $table) {
                // Drop incorrect status index if it exists
                $this->safeDropIndex('suppliers', 'suppliers_br_status_idx');
                
                // Add correct index on is_active
                if (Schema::hasColumn('suppliers', 'branch_id') && 
                    Schema::hasColumn('suppliers', 'is_active')) {
                    $this->safeAddIndex($table, ['branch_id', 'is_active'], 'suppliers_br_active_idx', 'suppliers');
                }
            });
        }

        // Fix 3: sales - Remove due_date index (column doesn't exist)
        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                // Drop incorrect due_date index if it exists
                $this->safeDropIndex('sales', 'sales_customer_id_due_date_index');
            });
        }

        // Fix 4: rental_invoices - Remove tenant_id index and add contract_id index
        if (Schema::hasTable('rental_invoices')) {
            Schema::table('rental_invoices', function (Blueprint $table) {
                // Drop incorrect tenant_id index if it exists
                $this->safeDropIndex('rental_invoices', ['tenant_id']);
                
                // Add correct index on contract_id (may already exist as FK)
                if (Schema::hasColumn('rental_invoices', 'contract_id')) {
                    $this->safeAddIndex($table, 'contract_id', 'rental_invoices_contract_idx', 'rental_invoices');
                }
            });
        }

        // Fix 5: tickets - Fix customer_id FK to point to customers instead of clients
        if (Schema::hasTable('tickets')) {
            // First, drop the incorrect foreign key if it exists
            $this->safeDropForeignKey('tickets', 'tickets_customer_id_foreign');
            
            Schema::table('tickets', function (Blueprint $table) {
                // Re-add the foreign key pointing to customers
                if (Schema::hasColumn('tickets', 'customer_id') && Schema::hasTable('customers')) {
                    $table->foreign('customer_id')
                        ->references('id')
                        ->on('customers')
                        ->nullOnDelete();
                }
            });
        }

        // Fix 6: projects - Fix client_id FK to point to customers instead of clients
        if (Schema::hasTable('projects')) {
            // First, drop the incorrect foreign key if it exists
            $this->safeDropForeignKey('projects', 'projects_client_id_foreign');
            
            Schema::table('projects', function (Blueprint $table) {
                // Re-add the foreign key pointing to customers
                if (Schema::hasColumn('projects', 'client_id') && Schema::hasTable('customers')) {
                    $table->foreign('client_id')
                        ->references('id')
                        ->on('customers')
                        ->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert audit_logs fixes
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $this->safeDropIndex('audit_logs', 'audit_logs_subject_idx');
                $this->safeDropIndex('audit_logs', 'audit_logs_action_idx');
            });
        }

        // Revert suppliers fixes
        if (Schema::hasTable('suppliers')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $this->safeDropIndex('suppliers', 'suppliers_br_active_idx');
            });
        }

        // Revert rental_invoices fixes
        if (Schema::hasTable('rental_invoices')) {
            Schema::table('rental_invoices', function (Blueprint $table) {
                $this->safeDropIndex('rental_invoices', 'rental_invoices_contract_idx');
            });
        }

        // Note: We don't revert FK changes to avoid breaking the database
        // The foreign keys now point to the correct tables
    }

    /**
     * Safely drop an index if it exists.
     *
     * @param  string  $table
     * @param  string|array  $index
     */
    private function safeDropIndex(string $table, string|array $index): void
    {
        try {
            $indexName = is_array($index) ? null : $index;
            
            if (is_array($index)) {
                // For column-based indexes, let Blueprint handle the name
                Schema::table($table, function (Blueprint $blueprint) use ($index) {
                    $blueprint->dropIndex($index);
                });
            } else {
                // For named indexes
                if ($this->indexExists($table, $indexName)) {
                    Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
                        $blueprint->dropIndex($indexName);
                    });
                }
            }
        } catch (\Exception $e) {
            // Index doesn't exist or can't be dropped, continue
        }
    }

    /**
     * Safely add an index if it doesn't exist.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $table
     * @param  string|array  $columns
     * @param  string|null  $indexName
     * @param  string  $tableName
     */
    private function safeAddIndex(Blueprint $table, string|array $columns, ?string $indexName = null, string $tableName = ''): void
    {
        try {
            if ($indexName && $tableName && $this->indexExists($tableName, $indexName)) {
                return;
            }
            
            if ($indexName) {
                $table->index($columns, $indexName);
            } else {
                $table->index($columns);
            }
        } catch (\Exception $e) {
            // Index already exists or can't be created, continue
        }
    }

    /**
     * Safely drop a foreign key if it exists.
     *
     * @param  string  $table
     * @param  string  $foreignKey
     */
    private function safeDropForeignKey(string $table, string $foreignKey): void
    {
        try {
            if ($this->foreignKeyExists($table, $foreignKey)) {
                Schema::table($table, function (Blueprint $blueprint) use ($foreignKey) {
                    $blueprint->dropForeign($foreignKey);
                });
            }
        } catch (\Exception $e) {
            // Foreign key doesn't exist or can't be dropped, continue
        }
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

    /**
     * Check if a foreign key exists on a table.
     *
     * @param  string  $table
     * @param  string  $foreignKey
     * @return bool
     */
    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        try {
            $foreignKeys = Schema::getForeignKeys($table);
            foreach ($foreignKeys as $fk) {
                if (strcasecmp($fk['name'], $foreignKey) === 0) {
                    return true;
                }
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
};
