<?php

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
                if (!$this->indexExists('sales', 'sales_reference_index')) {
                    $table->index('reference');
                }
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
                if (!$this->indexExists('products', 'products_is_active_index')) {
                    $table->index('is_active');
                }
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
        // Drop indexes - Note: Only drop if they exist
        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropIndex(['customer_id']);
                $table->dropIndex(['branch_id']);
                $table->dropIndex(['status']);
                $table->dropIndex(['posted_at']);
                $table->dropIndex(['reference']);
            });
        }

        if (Schema::hasTable('purchases')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->dropIndex(['supplier_id']);
                $table->dropIndex(['branch_id']);
                $table->dropIndex(['status']);
                $table->dropIndex(['posted_at']);
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex(['sku']);
                $table->dropIndex(['barcode']);
                $table->dropIndex(['category_id']);
                $table->dropIndex(['is_active']);
            });
        }

        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropIndex(['email']);
                $table->dropIndex(['phone']);
                $table->dropIndex(['branch_id']);
            });
        }

        if (Schema::hasTable('suppliers')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->dropIndex(['email']);
                $table->dropIndex(['phone']);
            });
        }

        if (Schema::hasTable('rental_contracts')) {
            Schema::table('rental_contracts', function (Blueprint $table) {
                $table->dropIndex(['status']);
                $table->dropIndex(['start_date']);
                $table->dropIndex(['end_date']);
            });
        }

        if (Schema::hasTable('hr_employees')) {
            Schema::table('hr_employees', function (Blueprint $table) {
                $table->dropIndex(['branch_id']);
                $table->dropIndex(['is_active']);
            });
        }

        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->dropIndex(['employee_id']);
                $table->dropIndex(['date']);
                $table->dropIndex(['status']);
            });
        }

        if (Schema::hasTable('stock_movements')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->dropIndex(['product_id']);
                $table->dropIndex(['warehouse_id']);
                $table->dropIndex(['created_at']);
            });
        }
    }

    /**
     * Check if an index exists
     */
    protected function indexExists(string $table, string $index): bool
    {
        $sm = Schema::getConnection()->getDoctrineSchemaManager();
        $indexes = $sm->listTableIndexes($table);
        
        return isset($indexes[$index]);
    }
};
