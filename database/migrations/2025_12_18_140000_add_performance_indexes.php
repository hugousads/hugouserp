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
        // Add indexes for frequently queried columns

        // Sales table indexes
        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                if (!$this->indexExists('sales', 'sales_customer_id_index')) {
                    $table->index('customer_id');
                }
                if (!$this->indexExists('sales', 'sales_branch_id_index')) {
                    $table->index('branch_id');
                }
                if (!$this->indexExists('sales', 'sales_status_index')) {
                    $table->index('status');
                }
                if (!$this->indexExists('sales', 'sales_posted_at_index')) {
                    $table->index('posted_at');
                }
                // Note: reference_no index already exists via unique constraint sales_branch_reference_unique
                // so we skip the separate reference_no index to avoid SQLite issues
            });
        }

        // Purchases table indexes
        if (Schema::hasTable('purchases')) {
            Schema::table('purchases', function (Blueprint $table) {
                if (!$this->indexExists('purchases', 'purchases_supplier_id_index')) {
                    $table->index('supplier_id');
                }
                if (!$this->indexExists('purchases', 'purchases_branch_id_index')) {
                    $table->index('branch_id');
                }
                if (!$this->indexExists('purchases', 'purchases_status_index')) {
                    $table->index('status');
                }
                if (!$this->indexExists('purchases', 'purchases_posted_at_index')) {
                    $table->index('posted_at');
                }
            });
        }

        // Products table indexes
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (!$this->indexExists('products', 'products_sku_index')) {
                    $table->index('sku');
                }
                if (!$this->indexExists('products', 'products_barcode_index')) {
                    $table->index('barcode');
                }
                if (!$this->indexExists('products', 'products_category_id_index')) {
                    $table->index('category_id');
                }
                // Note: status index already exists via composite prod_br_status_idx
                // so we skip the separate is_active index
            });
        }

        // Customers table indexes
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                if (!$this->indexExists('customers', 'customers_email_index')) {
                    $table->index('email');
                }
                if (!$this->indexExists('customers', 'customers_phone_index')) {
                    $table->index('phone');
                }
                if (!$this->indexExists('customers', 'customers_branch_id_index')) {
                    $table->index('branch_id');
                }
            });
        }

        // Suppliers table indexes
        if (Schema::hasTable('suppliers')) {
            Schema::table('suppliers', function (Blueprint $table) {
                if (!$this->indexExists('suppliers', 'suppliers_email_index')) {
                    $table->index('email');
                }
                if (!$this->indexExists('suppliers', 'suppliers_phone_index')) {
                    $table->index('phone');
                }
            });
        }

        // Rental contracts indexes
        if (Schema::hasTable('rental_contracts')) {
            Schema::table('rental_contracts', function (Blueprint $table) {
                if (!$this->indexExists('rental_contracts', 'rental_contracts_status_index')) {
                    $table->index('status');
                }
                if (!$this->indexExists('rental_contracts', 'rental_contracts_start_date_index')) {
                    $table->index('start_date');
                }
                if (!$this->indexExists('rental_contracts', 'rental_contracts_end_date_index')) {
                    $table->index('end_date');
                }
            });
        }

        // HR employees indexes
        if (Schema::hasTable('hr_employees')) {
            Schema::table('hr_employees', function (Blueprint $table) {
                if (!$this->indexExists('hr_employees', 'hr_employees_branch_id_index')) {
                    $table->index('branch_id');
                }
                if (!$this->indexExists('hr_employees', 'hr_employees_is_active_index')) {
                    $table->index('is_active');
                }
            });
        }

        // Attendance indexes
        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', function (Blueprint $table) {
                if (!$this->indexExists('attendances', 'attendances_employee_id_index')) {
                    $table->index('employee_id');
                }
                if (!$this->indexExists('attendances', 'attendances_date_index')) {
                    $table->index('date');
                }
                if (!$this->indexExists('attendances', 'attendances_status_index')) {
                    $table->index('status');
                }
            });
        }

        // Stock movements indexes
        if (Schema::hasTable('stock_movements')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                if (!$this->indexExists('stock_movements', 'stock_movements_product_id_index')) {
                    $table->index('product_id');
                }
                if (!$this->indexExists('stock_movements', 'stock_movements_warehouse_id_index')) {
                    $table->index('warehouse_id');
                }
                if (!$this->indexExists('stock_movements', 'stock_movements_created_at_index')) {
                    $table->index('created_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes - wrapped in try-catch for safety
        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                try {
                    $table->dropIndex(['customer_id']);
                } catch (\Exception $e) {
                    // Index may not exist
                }
                try {
                    $table->dropIndex(['branch_id']);
                } catch (\Exception $e) {
                    // Index may not exist
                }
                try {
                    $table->dropIndex(['status']);
                } catch (\Exception $e) {
                    // Index may not exist
                }
                try {
                    $table->dropIndex(['posted_at']);
                } catch (\Exception $e) {
                    // Index may not exist
                }
                // Note: reference_no index not created separately (uses unique constraint)
            });
        }

        if (Schema::hasTable('purchases')) {
            Schema::table('purchases', function (Blueprint $table) {
                try {
                    $table->dropIndex(['supplier_id']);
                } catch (\Exception $e) {
                    // Index may not exist
                }
                try {
                    $table->dropIndex(['branch_id']);
                } catch (\Exception $e) {
                    // Index may not exist
                }
                try {
                    $table->dropIndex(['status']);
                } catch (\Exception $e) {
                    // Index may not exist
                }
                try {
                    $table->dropIndex(['posted_at']);
                } catch (\Exception $e) {
                    // Index may not exist
                }
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                try {
                    $table->dropIndex(['sku']);
                } catch (\Exception $e) {
                    // Index may not exist
                }
                try {
                    $table->dropIndex(['barcode']);
                } catch (\Exception $e) {
                    // Index may not exist
                }
                try {
                    $table->dropIndex(['category_id']);
                } catch (\Exception $e) {
                    // Index may not exist
                }
                // Note: is_active index not created separately (uses composite prod_br_status_idx)
            });
        }

        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                try {
                    $table->dropIndex(['email']);
                } catch (\Exception $e) {
                    // Index may not exist
                }
                try {
                    $table->dropIndex(['phone']);
                } catch (\Exception $e) {
                    // Index may not exist
                }
                try {
                    $table->dropIndex(['branch_id']);
                } catch (\Exception $e) {
                    // Index may not exist
                }
            });
        }

        if (Schema::hasTable('suppliers')) {
            Schema::table('suppliers', function (Blueprint $table) {
                try {
                    $table->dropIndex(['email']);
                } catch (\Exception $e) {
                    // Index may not exist
                }
                try {
                    $table->dropIndex(['phone']);
                } catch (\Exception $e) {
                    // Index may not exist
                }
            });
        }

        if (Schema::hasTable('rental_contracts')) {
            Schema::table('rental_contracts', function (Blueprint $table) {
                try {
                    $table->dropIndex(['status']);
                } catch (\Exception $e) {
                    // Index may not exist
                }
                try {
                    $table->dropIndex(['start_date']);
                } catch (\Exception $e) {
                    // Index may not exist
                }
                try {
                    $table->dropIndex(['end_date']);
                } catch (\Exception $e) {
                    // Index may not exist
                }
            });
        }

        if (Schema::hasTable('hr_employees')) {
            Schema::table('hr_employees', function (Blueprint $table) {
                try {
                    $table->dropIndex(['branch_id']);
                } catch (\Exception $e) {
                    // Index may not exist
                }
                try {
                    $table->dropIndex(['is_active']);
                } catch (\Exception $e) {
                    // Index may not exist
                }
            });
        }

        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', function (Blueprint $table) {
                try {
                    $table->dropIndex(['employee_id']);
                } catch (\Exception $e) {
                    // Index may not exist
                }
                try {
                    $table->dropIndex(['date']);
                } catch (\Exception $e) {
                    // Index may not exist
                }
                try {
                    $table->dropIndex(['status']);
                } catch (\Exception $e) {
                    // Index may not exist
                }
            });
        }

        if (Schema::hasTable('stock_movements')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                try {
                    $table->dropIndex(['product_id']);
                } catch (\Exception $e) {
                    // Index may not exist
                }
                try {
                    $table->dropIndex(['warehouse_id']);
                } catch (\Exception $e) {
                    // Index may not exist
                }
                try {
                    $table->dropIndex(['created_at']);
                } catch (\Exception $e) {
                    // Index may not exist
                }
            });
        }
    }

    /**
     * Check if an index exists
     * Compatible with Laravel 12+ (no Doctrine dependency)
     */
    protected function indexExists(string $table, string $index): bool
    {
        try {
            $connection = Schema::getConnection();
            $schemaBuilder = $connection->getSchemaBuilder();

            // Get all indexes for the table
            $indexes = $schemaBuilder->getIndexes($table);

            // Check if index exists by name
            foreach ($indexes as $indexInfo) {
                if ($indexInfo['name'] === $index) {
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            // If we can't determine, assume it doesn't exist to allow creation attempt
            // Laravel will handle duplicate index errors gracefully
            return false;
        }
    }
};
