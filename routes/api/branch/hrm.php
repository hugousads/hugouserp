<?php

use App\Http\Controllers\Branch\HRM\AttendanceController as BranchAttendanceController;
use App\Http\Controllers\Branch\HRM\EmployeeController as BranchEmployeeController;
use App\Http\Controllers\Branch\HRM\ExportImportController as BranchHRMExportImportController;
use App\Http\Controllers\Branch\HRM\PayrollController as BranchPayrollController;
use App\Http\Controllers\Branch\HRM\ReportsController as BranchHRMReportsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Branch HRM Routes
| Parent group (/api/v1/branches/{branch}) already applies:
|   - api-core
|   - api-auth
|   - api-branch
|--------------------------------------------------------------------------
*/

Route::prefix('hrm')->group(function () {

    // ==================== Employees ====================
    Route::get('employees', [BranchEmployeeController::class, 'index'])
        ->middleware('perm:hrm.employees.view');

    Route::get('employees/{employee}', [BranchEmployeeController::class, 'show'])
        ->middleware('perm:hrm.employees.view');

    Route::post('employees/assign', [BranchEmployeeController::class, 'assign'])
        ->middleware('perm:hrm.employees.assign');

    Route::post('employees/{employee}/unassign', [BranchEmployeeController::class, 'unassign'])
        ->middleware('perm:hrm.employees.unassign');

    // ==================== Attendance ====================
    Route::get('attendance', [BranchAttendanceController::class, 'index'])
        ->middleware('perm:hrm.attendance.view');

    // تسجيل حضور أو انصراف
    Route::post('attendance/log', [BranchAttendanceController::class, 'log'])
        ->middleware('perm:hrm.attendance.log');

    // الموافقة على سجل الحضور
    Route::post('attendance/{record}/approve', [BranchAttendanceController::class, 'approve'])
        ->middleware('perm:hrm.attendance.approve');

    // مضاف حديثًا: إنشاء/تحديث/تعطيل سجل الحضور (بحسب الجدول المركزي)
    Route::post('attendance', [BranchAttendanceController::class, 'store'])
        ->middleware('perm:hrm.attendance.create');

    Route::match(['put', 'patch'], 'attendance/{record}', [BranchAttendanceController::class, 'update'])
        ->middleware('perm:hrm.attendance.update');

    Route::post('attendance/{record}/deactivate', [BranchAttendanceController::class, 'deactivate'])
        ->middleware('perm:hrm.attendance.deactivate');

    // ==================== Payroll ====================
    Route::get('payroll', [BranchPayrollController::class, 'index'])
        ->middleware('perm:hrm.payroll.view');

    Route::post('payroll/run', [BranchPayrollController::class, 'run'])
        ->middleware('perm:hrm.payroll.run');

    Route::post('payroll/{payroll}/approve', [BranchPayrollController::class, 'approve'])
        ->middleware('perm:hrm.payroll.approve');

    Route::post('payroll/{payroll}/pay', [BranchPayrollController::class, 'pay'])
        ->middleware('perm:hrm.payroll.pay');

    // ==================== Export/Import ====================
    Route::get('export/employees', [BranchHRMExportImportController::class, 'exportEmployees'])
        ->middleware('perm:hrm.employees.export');

    Route::post('import/employees', [BranchHRMExportImportController::class, 'importEmployees'])
        ->middleware('perm:hrm.employees.import');

    // ==================== Reports ====================
    Route::get('reports/attendance', [BranchHRMReportsController::class, 'attendance'])
        ->middleware('perm:hrm.reports.view');

    Route::get('reports/payroll', [BranchHRMReportsController::class, 'payroll'])
        ->middleware('perm:hrm.reports.view');
});
