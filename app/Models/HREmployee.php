<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HREmployee extends BaseModel
{
    protected $table = 'hr_employees';

    protected ?string $moduleKey = 'hr';

    protected $fillable = [
        'branch_id', 'user_id', 'code', 'employee_code', 'name', 'email', 'phone', 'national_id',
        'date_of_birth', 'gender', 'address', 'position', 'department', 'hire_date', 'salary', 'salary_type',
        'employment_type', 'status', 'termination_date', 'bank_account_number', 'bank_name', 'is_active',
        'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation',
        'contract_start_date', 'contract_end_date', 'work_permit_number', 'work_permit_expiry',
        'extra_attributes'
    ];

    protected $casts = [
        'salary' => 'decimal:2',
        'is_active' => 'bool',
        'date_of_birth' => 'date',
        'hire_date' => 'date',
        'termination_date' => 'date',
        'contract_start_date' => 'date',
        'contract_end_date' => 'date',
        'work_permit_expiry' => 'date'
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    // Many-to-many relationship with branches via pivot table
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_employee', 'employee_id', 'branch_id')
            ->withPivot(['is_primary', 'assigned_at', 'detached_at'])
            ->withTimestamps();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'employee_id');
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class, 'employee_id');
    }

    public function employeeShifts(): HasMany
    {
        return $this->hasMany(EmployeeShift::class, 'employee_id');
    }

    public function shifts()
    {
        return $this->belongsToMany(Shift::class, 'employee_shifts', 'employee_id', 'shift_id')
            ->withPivot(['start_date', 'end_date', 'is_active'])
            ->withTimestamps();
    }

    public function currentShift()
    {
        return $this->employeeShifts()
            ->where('is_active', true)
            ->where('start_date', '<=', now()->toDateString())
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now()->toDateString());
            })
            ->with('shift')
            ->first();
    }
}
