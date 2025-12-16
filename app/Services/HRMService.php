<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Attendance;
use App\Models\HREmployee;
use App\Models\Payroll;
use App\Services\Contracts\HRMServiceInterface;
use App\Traits\HandlesServiceErrors;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Factory as ValidatorFactory;

class HRMService implements HRMServiceInterface
{
    use HandlesServiceErrors;

    public function __construct(protected ValidatorFactory $validator) {}

    /** @return \Illuminate\Database\Eloquent\Collection<int, HREmployee> */
    public function employees(bool $activeOnly = true)
    {
        return HREmployee::query()
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderBy('id', 'desc')
            ->get();
    }

    public function logAttendance(int $employeeId, string $type, string $at): Attendance
    {
        return $this->handleServiceOperation(
            callback: function () use ($employeeId, $type, $at) {
                $this->validator->make(['type' => $type], ['type' => 'required|in:in,out'])->validate();
                $ts = Carbon::parse($at);
                $date = $ts->toDateString();
                $employee = HREmployee::findOrFail($employeeId);
                $branchId = $employee->branch_id;

                $attendance = Attendance::firstOrNew([
                    'employee_id' => $employeeId,
                    'date' => $date,
                ], [
                    'branch_id' => $branchId,
                    'status' => 'pending',
                ]);

                if ($type === 'in') {
                    $attendance->check_in = $ts;
                } else {
                    $attendance->check_out = $ts;
                }

                $attendance->save();

                return $attendance;
            },
            operation: 'logAttendance',
            context: ['employee_id' => $employeeId, 'type' => $type, 'at' => $at]
        );
    }

    public function approveAttendance(int $attendanceId): Attendance
    {
        return $this->handleServiceOperation(
            callback: function () use ($attendanceId) {
                $att = Attendance::findOrFail($attendanceId);
                $att->status = 'approved';
                $att->approved_by = auth()->id();
                $att->approved_at = now();
                $att->save();

                return $att;
            },
            operation: 'approveAttendance',
            context: ['attendance_id' => $attendanceId]
        );
    }

    public function runPayroll(string $period): int
    {
        return $this->handleServiceOperation(
            callback: function () use ($period) {
                $emps = HREmployee::query()->where('is_active', true)->get();
                $count = 0;
                DB::transaction(function () use ($emps, $period, &$count) {
                    foreach ($emps as $emp) {
                        $exists = Payroll::query()
                            ->where('employee_id', $emp->getKey())
                            ->where('period', $period)->exists();
                        if ($exists) {
                            continue;
                        }

                        $basic = (float) $emp->salary;

                        $extra = $emp->extra_attributes ?? [];
                        $housingAllowance = (float) ($extra['housing_allowance'] ?? 0);
                        $transportAllowance = (float) ($extra['transport_allowance'] ?? 0);
                        $otherAllowance = (float) ($extra['other_allowance'] ?? 0);
                        $totalAllowances = $housingAllowance + $transportAllowance + $otherAllowance;

                        $grossSalary = $basic + $totalAllowances;
                        $socialInsurance = $this->calculateSocialInsurance($grossSalary);
                        $tax = $this->calculateTax($grossSalary - $socialInsurance);
                        $absenceDeduction = $this->calculateAbsenceDeduction($emp, $period);
                        $loanDeduction = (float) ($extra['loan_deduction'] ?? 0);
                        $totalDeductions = $socialInsurance + $tax + $absenceDeduction + $loanDeduction;

                        $net = $grossSalary - $totalDeductions;

                        Payroll::create([
                            'employee_id' => $emp->getKey(),
                            'period' => $period,
                            'basic' => $basic,
                            'allowances' => $totalAllowances,
                            'deductions' => $totalDeductions,
                            'net' => max(0, $net),
                            'status' => 'pending',
                        ]);
                        $count++;
                    }
                });

                return $count;
            },
            operation: 'runPayroll',
            context: ['period' => $period]
        );
    }

    protected function calculateSocialInsurance(float $grossSalary): float
    {
        $rate = config('hrm.social_insurance.rate', 0.14);
        $maxSalary = config('hrm.social_insurance.max_salary', 12600);

        $insurableSalary = min($grossSalary, $maxSalary);

        return round($insurableSalary * $rate, 2);
    }

    protected function calculateTax(float $taxableIncome): float
    {
        $annualIncome = $taxableIncome * 12;
        $annualTax = 0;

        $brackets = config('hrm.tax_brackets', [
            ['limit' => 40000, 'rate' => 0],
            ['limit' => 55000, 'rate' => 0.10],
            ['limit' => 70000, 'rate' => 0.15],
            ['limit' => 200000, 'rate' => 0.20],
            ['limit' => 400000, 'rate' => 0.225],
            ['limit' => PHP_FLOAT_MAX, 'rate' => 0.25],
        ]);

        $previousLimit = 0;
        foreach ($brackets as $bracket) {
            if ($annualIncome <= $previousLimit) {
                break;
            }

            $taxableInBracket = min($annualIncome, $bracket['limit']) - $previousLimit;
            $annualTax += max(0, $taxableInBracket) * $bracket['rate'];
            $previousLimit = $bracket['limit'];
        }

        return round($annualTax / 12, 2);
    }

    protected function calculateAbsenceDeduction(HREmployee $emp, string $period): float
    {
        try {
            $periodDate = Carbon::createFromFormat('Y-m', $period);
            if (! $periodDate) {
                return 0;
            }

            $startDate = $periodDate->copy()->startOfMonth()->toDateString();
            $endDate = $periodDate->copy()->endOfMonth()->toDateString();

            $absenceDays = Attendance::query()
                ->where('employee_id', $emp->getKey())
                ->where('status', 'absent')
                ->whereBetween('date', [$startDate, $endDate])
                ->count();

            if ($absenceDays <= 0) {
                return 0;
            }

            $dailyRate = (float) $emp->salary / 30;

            return round($dailyRate * $absenceDays, 2);
        } catch (\Exception $e) {
            Log::warning('Failed to calculate absence deduction', [
                'employee_id' => $emp->getKey(),
                'period' => $period,
                'error' => $e->getMessage(),
            ]);
            
            return 0;
        }
    }
}
