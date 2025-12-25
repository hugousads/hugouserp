<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;

$tables = [
    'suppliers' => App\Models\Supplier::class,
    'sales' => App\Models\Sale::class,
    'purchases' => App\Models\Purchase::class,
    'products' => App\Models\Product::class,
    'hr_employees' => App\Models\HREmployee::class,
    'rental_contracts' => App\Models\RentalContract::class,
    'tenants' => App\Models\Tenant::class,
    'audit_logs' => App\Models\AuditLog::class,
    'ticket_replies' => App\Models\TicketReply::class,
    'project_expenses' => App\Models\ProjectExpense::class,
];

foreach ($tables as $table => $class) {
    echo "\n--- $table ---\n";
    
    $model = new $class();
    $fillable = $model->getFillable();
    $columns = Schema::getColumnListing($table);
    
    $missingInDb = array_diff($fillable, $columns);
    $missingInModel = array_diff($columns, array_merge($fillable, ['id', 'created_at', 'updated_at', 'deleted_at', 'uuid', 'code']));
    
    if (!empty($missingInDb)) {
        echo "FILLABLE MISSING IN DB: " . implode(', ', $missingInDb) . "\n";
    }
    if (!empty($missingInModel)) {
        echo "DB COLUMNS NOT IN FILLABLE: " . implode(', ', $missingInModel) . "\n";
    }
    if (empty($missingInDb) && empty($missingInModel)) {
        echo "OK\n";
    }
}

echo "\n\n=== Checking additional models ===\n";

$additionalModels = [
    'rental_invoices' => App\Models\RentalInvoice::class,
    'rental_payments' => App\Models\RentalPayment::class,
    'attendances' => App\Models\Attendance::class,
    'payrolls' => App\Models\Payroll::class,
    'leave_requests' => App\Models\LeaveRequest::class,
    'project_time_logs' => App\Models\ProjectTimeLog::class,
    'warehouses' => App\Models\Warehouse::class,
    'vehicles' => App\Models\Vehicle::class,
    'vehicle_contracts' => App\Models\VehicleContract::class,
    'warranties' => App\Models\Warranty::class,
    'properties' => App\Models\Property::class,
    'rental_units' => App\Models\RentalUnit::class,
    'receipts' => App\Models\Receipt::class,
    'deliveries' => App\Models\Delivery::class,
    'adjustments' => App\Models\Adjustment::class,
    'transfers' => App\Models\Transfer::class,
    'stock_movements' => App\Models\StockMovement::class,
];

foreach ($additionalModels as $table => $class) {
    echo "\n--- $table ---\n";
    try {
        $model = new $class();
        $fillable = $model->getFillable();
        $columns = Schema::getColumnListing($table);
        
        $missingInDb = array_diff($fillable, $columns);
        $missingInModel = array_diff($columns, array_merge($fillable, ['id', 'created_at', 'updated_at', 'deleted_at', 'uuid', 'code']));
        
        if (!empty($missingInDb)) {
            echo "FILLABLE MISSING IN DB: " . implode(', ', $missingInDb) . "\n";
        }
        if (!empty($missingInModel)) {
            echo "DB COLUMNS NOT IN FILLABLE: " . implode(', ', $missingInModel) . "\n";
        }
        if (empty($missingInDb) && empty($missingInModel)) {
            echo "OK\n";
        }
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}
