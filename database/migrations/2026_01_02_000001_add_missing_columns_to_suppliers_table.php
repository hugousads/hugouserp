<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add missing columns to suppliers table.
     * 
     * BUG FIX: Form fields (city, country, company_name, minimum_order_value, supplier_rating)
     * were not persisting because the corresponding database columns were missing.
     */
    public function up(): void
    {
        if (Schema::hasTable('suppliers')) {
            Schema::table('suppliers', function (Blueprint $table) {
                // Add city column (used in form but missing from DB)
                if (!Schema::hasColumn('suppliers', 'city')) {
                    $table->string('city', 100)->nullable()->after('address')->comment('City');
                }
                
                // Add country column (used in form but missing from DB)
                if (!Schema::hasColumn('suppliers', 'country')) {
                    $table->string('country', 100)->nullable()->after('city')->comment('Country');
                }
                
                // Add company_name column (used in form but missing from DB)
                if (!Schema::hasColumn('suppliers', 'company_name')) {
                    $table->string('company_name')->nullable()->after('name')->comment('Company Name');
                }
                
                // Add minimum_order_value column (used in form but missing from DB)
                if (!Schema::hasColumn('suppliers', 'minimum_order_value')) {
                    $table->decimal('minimum_order_value', 18, 2)->default(0)->after('preferred_currency')->comment('Minimum order value');
                }
                
                // Add supplier_rating column (used in form but missing from DB)
                if (!Schema::hasColumn('suppliers', 'supplier_rating')) {
                    $table->string('supplier_rating', 191)->nullable()->after('minimum_order_value')->comment('Supplier rating (text field)');
                }
                
                // Add last_purchase_date column (referenced in model)
                if (!Schema::hasColumn('suppliers', 'last_purchase_date')) {
                    $table->timestamp('last_purchase_date')->nullable()->after('supplier_rating')->comment('Last purchase date');
                }
                
                // Add created_by column for audit trail
                if (!Schema::hasColumn('suppliers', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->after('last_purchase_date')->comment('created_by');
                }
                
                // Add updated_by column for audit trail
                if (!Schema::hasColumn('suppliers', 'updated_by')) {
                    $table->unsignedBigInteger('updated_by')->nullable()->after('created_by')->comment('updated_by');
                }
                
                // Add foreign keys if columns were just created
                if (Schema::hasColumn('suppliers', 'created_by') && !$this->foreignKeyExists('suppliers', 'suppliers_created_by_foreign')) {
                    $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                }
                
                if (Schema::hasColumn('suppliers', 'updated_by') && !$this->foreignKeyExists('suppliers', 'suppliers_updated_by_foreign')) {
                    $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
                }
            });
        }
    }

    /**
     * Check if a foreign key exists
     */
    protected function foreignKeyExists(string $table, string $foreignKey): bool
    {
        try {
            $connection = Schema::getConnection();
            $schemaBuilder = $connection->getSchemaBuilder();
            $foreignKeys = $schemaBuilder->getForeignKeys($table);
            
            foreach ($foreignKeys as $fk) {
                if ($fk['name'] === $foreignKey) {
                    return true;
                }
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('suppliers')) {
            Schema::table('suppliers', function (Blueprint $table) {
                // Drop foreign keys first
                try {
                    $table->dropForeign(['created_by']);
                } catch (\Exception $e) {
                    // Foreign key may not exist
                }
                
                try {
                    $table->dropForeign(['updated_by']);
                } catch (\Exception $e) {
                    // Foreign key may not exist
                }
                
                // Drop columns
                $columns = ['city', 'country', 'company_name', 'minimum_order_value', 'supplier_rating', 'last_purchase_date', 'created_by', 'updated_by'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('suppliers', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
