<?php

use App\Http\Controllers\Branch\Rental\ContractController;
use App\Http\Controllers\Branch\Rental\ExportImportController as BranchRentalExportImportController;
use App\Http\Controllers\Branch\Rental\InvoiceController;
use App\Http\Controllers\Branch\Rental\PropertyController;
use App\Http\Controllers\Branch\Rental\ReportsController as BranchRentalReportsController;
use App\Http\Controllers\Branch\Rental\TenantController;
use App\Http\Controllers\Branch\Rental\UnitController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rental Module (under /api/v1/branches/{branch}/modules/rental)
| Parent groups already apply: api-core + api-auth + api-branch
| Here we enforce module.enabled and add fine-grained permissions.
|--------------------------------------------------------------------------
*/

Route::prefix('modules/rental')->middleware(['module.enabled:rental'])->group(function () {

    // ==================== Properties ====================
    Route::prefix('properties')->group(function () {
        Route::get('/', [PropertyController::class, 'index'])
            ->middleware('perm:rental.properties.view');

        Route::post('/', [PropertyController::class, 'store'])
            ->middleware('perm:rental.properties.create');

        Route::get('{property}', [PropertyController::class, 'show'])
            ->middleware('perm:rental.properties.view');

        Route::match(['put', 'patch'], '{property}', [PropertyController::class, 'update'])
            ->middleware('perm:rental.properties.update');
    });

    // ==================== Units ====================
    Route::prefix('units')->group(function () {
        Route::get('/', [UnitController::class, 'index'])
            ->middleware('perm:rental.units.view');

        Route::post('/', [UnitController::class, 'store'])
            ->middleware('perm:rental.units.create');

        Route::get('{unit}', [UnitController::class, 'show'])
            ->middleware('perm:rental.units.view');

        Route::match(['put', 'patch'], '{unit}', [UnitController::class, 'update'])
            ->middleware('perm:rental.units.update');

        Route::post('{unit}/status', [UnitController::class, 'setStatus'])
            ->middleware('perm:rental.units.status');
    });

    // ==================== Tenants ====================
    Route::prefix('tenants')->group(function () {
        Route::get('/', [TenantController::class, 'index'])
            ->middleware('perm:rental.tenants.view');

        Route::post('/', [TenantController::class, 'store'])
            ->middleware('perm:rental.tenants.create');

        Route::get('{tenant}', [TenantController::class, 'show'])
            ->middleware('perm:rental.tenants.view');

        Route::match(['put', 'patch'], '{tenant}', [TenantController::class, 'update'])
            ->middleware('perm:rental.tenants.update');

        Route::post('{tenant}/archive', [TenantController::class, 'archive'])
            ->middleware('perm:rental.tenants.archive');
    });

    // ==================== Contracts ====================
    Route::prefix('contracts')->group(function () {
        Route::get('/', [ContractController::class, 'index'])
            ->middleware('perm:rental.contracts.view');

        Route::post('/', [ContractController::class, 'store'])
            ->middleware('perm:rental.contracts.create');

        Route::get('{contract}', [ContractController::class, 'show'])
            ->middleware('perm:rental.contracts.view');

        Route::match(['put', 'patch'], '{contract}', [ContractController::class, 'update'])
            ->middleware('perm:rental.contracts.update');

        Route::post('{contract}/renew', [ContractController::class, 'renew'])
            ->middleware('perm:rental.contracts.renew');

        Route::post('{contract}/terminate', [ContractController::class, 'terminate'])
            ->middleware('perm:rental.contracts.terminate');
    });

    // ==================== Invoices ====================
    Route::prefix('invoices')->group(function () {
        Route::get('/', [InvoiceController::class, 'index'])
            ->middleware('perm:rental.invoices.view');

        Route::get('{invoice}', [InvoiceController::class, 'show'])
            ->middleware('perm:rental.invoices.view');

        Route::post('run-recurring', [InvoiceController::class, 'runRecurring'])
            ->middleware('perm:rental.invoices.runRecurring');

        Route::post('{invoice}/collect', [InvoiceController::class, 'collectPayment'])
            ->middleware('perm:rental.invoices.collect');

        Route::post('{invoice}/penalty', [InvoiceController::class, 'applyPenalty'])
            ->middleware('perm:rental.invoices.penalty');
    });

    // ==================== Export/Import ====================
    Route::get('export/units', [BranchRentalExportImportController::class, 'exportUnits'])
        ->middleware('perm:rental.units.export');

    Route::get('export/tenants', [BranchRentalExportImportController::class, 'exportTenants'])
        ->middleware('perm:rental.tenants.export');

    Route::get('export/contracts', [BranchRentalExportImportController::class, 'exportContracts'])
        ->middleware('perm:rental.contracts.export');

    Route::post('import/units', [BranchRentalExportImportController::class, 'importUnits'])
        ->middleware('perm:rental.units.import');

    Route::post('import/tenants', [BranchRentalExportImportController::class, 'importTenants'])
        ->middleware('perm:rental.tenants.import');

    // ==================== Reports ====================
    Route::get('reports/occupancy', [BranchRentalReportsController::class, 'occupancy'])
        ->middleware('perm:rental.reports.view');

    Route::get('reports/expiring-contracts', [BranchRentalReportsController::class, 'expiringContracts'])
        ->middleware('perm:rental.reports.view');
});
