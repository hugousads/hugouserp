<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Branch;
use App\Models\Payroll;
use Illuminate\Support\Facades\View;

class PayslipService
{
    /**
     * Generate payslip HTML content
     */
    public function generatePayslipHtml(Payroll $payroll): string
    {
        $employee = $payroll->employee;
        $branch = $employee->branch;

        $data = [
            'payroll' => $payroll,
            'employee' => $employee,
            'branch' => $branch,
            'generatedAt' => now()->format('Y-m-d H:i:s'),
        ];

        return View::make('payslips.template', $data)->render();
    }

    /**
     * Get payslip breakdown
     */
    public function getPayslipBreakdown(Payroll $payroll): array
    {
        return [
            'basic_salary' => [
                'label' => __('Basic Salary'),
                'amount' => $payroll->basic,
                'type' => 'earning',
            ],
            'allowances' => [
                'label' => __('Allowances'),
                'amount' => $payroll->allowances,
                'type' => 'earning',
            ],
            'gross_salary' => [
                'label' => __('Gross Salary'),
                'amount' => $payroll->basic + $payroll->allowances,
                'type' => 'subtotal',
            ],
            'deductions' => [
                'label' => __('Deductions'),
                'amount' => $payroll->deductions,
                'type' => 'deduction',
            ],
            'net_salary' => [
                'label' => __('Net Salary'),
                'amount' => $payroll->net,
                'type' => 'total',
            ],
        ];
    }

    /**
     * Calculate allowances based on company rules from settings
     */
    protected function calculateAllowances(float $basicSalary): array
    {
        $allowances = [];
        $total = '0';

        // Transportation allowance (configurable percentage or fixed)
        $transportType = setting('hrm.transport_allowance_type', 'percentage');
        $transportValue = (float) setting('hrm.transport_allowance_value', 10);
        if ($transportType === 'percentage') {
            $transportAmount = bcmul((string) $basicSalary, bcdiv((string) $transportValue, '100', 4), 2);
        } else {
            $transportAmount = bcdiv((string) $transportValue, '1', 2);
        }
        if (bccomp($transportAmount, '0', 2) > 0) {
            $allowances['transport'] = (float) $transportAmount;
            $total = bcadd($total, $transportAmount, 2);
        }

        // Housing allowance (configurable)
        $housingType = setting('hrm.housing_allowance_type', 'percentage');
        $housingValue = (float) setting('hrm.housing_allowance_value', 0);
        if ($housingType === 'percentage') {
            $housingAmount = bcmul((string) $basicSalary, bcdiv((string) $housingValue, '100', 4), 2);
        } else {
            $housingAmount = bcdiv((string) $housingValue, '1', 2);
        }
        if (bccomp($housingAmount, '0', 2) > 0) {
            $allowances['housing'] = (float) $housingAmount;
            $total = bcadd($total, $housingAmount, 2);
        }

        // Meal allowance (fixed)
        $mealAllowance = (float) setting('hrm.meal_allowance', 0);
        if ($mealAllowance > 0) {
            $mealAllowanceStr = bcdiv((string) $mealAllowance, '1', 2);
            $allowances['meal'] = (float) $mealAllowanceStr;
            $total = bcadd($total, $mealAllowanceStr, 2);
        }

        return [
            'breakdown' => $allowances,
            'total' => (float) $total,
        ];
    }

    /**
     * Calculate deductions based on company rules and tax config
     */
    protected function calculateDeductions(float $grossSalary): array
    {
        $deductions = [];
        $total = '0';

        // Social Insurance deduction (use bcmath)
        $siConfig = config('hrm.social_insurance', []);
        $siRate = (float) ($siConfig['rate'] ?? 0.14);
        $siMaxSalary = (float) ($siConfig['max_salary'] ?? 12600);
        $siBaseSalary = bccomp((string) $grossSalary, (string) $siMaxSalary, 2) > 0 ? $siMaxSalary : $grossSalary;
        $socialInsurance = bcmul((string) $siBaseSalary, (string) $siRate, 2);
        if (bccomp($socialInsurance, '0', 2) > 0) {
            $deductions['social_insurance'] = (float) $socialInsurance;
            $total = bcadd($total, $socialInsurance, 2);
        }

        // Income Tax (progressive brackets)
        $annualGross = $grossSalary * 12;
        $taxBrackets = config('hrm.tax_brackets', []);
        $annualTax = 0.0;
        $previousLimit = 0;

        foreach ($taxBrackets as $bracket) {
            $limit = (float) ($bracket['limit'] ?? PHP_FLOAT_MAX);
            $rate = (float) ($bracket['rate'] ?? 0);

            if ($annualGross <= $previousLimit) {
                break;
            }

            $taxableInBracket = min($annualGross, $limit) - $previousLimit;
            $annualTax += max(0, $taxableInBracket) * $rate;
            $previousLimit = $limit;
        }

        $monthlyTax = $annualTax / 12;
        if ($monthlyTax > 0) {
            $deductions['income_tax'] = round($monthlyTax, 2);
            $total += $monthlyTax;
        }

        // Additional fixed deductions from settings
        $healthInsurance = (float) setting('hrm.health_insurance_deduction', 0);
        if ($healthInsurance > 0) {
            $healthInsuranceStr = bcdiv((string) $healthInsurance, '1', 2);
            $deductions['health_insurance'] = (float) $healthInsuranceStr;
            $total = bcadd($total, $healthInsuranceStr, 2);
        }

        return [
            'breakdown' => $deductions,
            'total' => (float) $total,
        ];
    }

    /**
     * Calculate payroll for employee
     */
    public function calculatePayroll(int $employeeId, string $period): array
    {
        $employee = \App\Models\HREmployee::findOrFail($employeeId);

        // Basic salary from employee record
        $basic = (float) $employee->salary;

        // Calculate allowances based on configurable company rules
        $allowanceResult = $this->calculateAllowances($basic);
        $allowances = $allowanceResult['total'];

        // Gross salary
        $gross = $basic + $allowances;

        // Calculate deductions based on configurable rules and tax brackets
        $deductionResult = $this->calculateDeductions($gross);
        $deductions = $deductionResult['total'];

        // Net salary (use bcmath)
        $net = bcsub((string) $gross, (string) $deductions, 2);

        return [
            'employee_id' => $employeeId,
            'period' => $period,
            'basic' => (float) bcdiv((string) $basic, '1', 2),
            'allowances' => (float) bcdiv((string) $allowances, '1', 2),
            'allowance_breakdown' => $allowanceResult['breakdown'],
            'deductions' => (float) bcdiv((string) $deductions, '1', 2),
            'deduction_breakdown' => $deductionResult['breakdown'],
            'gross' => (float) bcdiv((string) $gross, '1', 2),
            'net' => (float) $net,
            'status' => 'draft',
        ];
    }

    /**
     * Process payroll for all employees in a branch
     */
    public function processBranchPayroll(int $branchId, string $period): array
    {
        $employees = \App\Models\HREmployee::where('branch_id', $branchId)
            ->where('is_active', true)
            ->get();

        $processed = [];
        $errors = [];

        // Parse period to extract year and month
        $periodParts = explode('-', $period);
        $year = (int) ($periodParts[0] ?? date('Y'));
        $month = (int) ($periodParts[1] ?? date('m'));

        foreach ($employees as $employee) {
            try {
                // Check if payroll already exists for this employee in this period
                // regardless of branch to prevent duplicate payroll when employee changes department
                $existingPayroll = Payroll::where('employee_id', $employee->id)
                    ->where('year', $year)
                    ->where('month', $month)
                    ->first();

                if ($existingPayroll) {
                    $errors[] = [
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->name,
                        'error' => __('Payroll already generated for this employee in this period'),
                    ];

                    continue;
                }

                $payrollData = $this->calculatePayroll($employee->id, $period);

                // Only store the fields that match the Payroll model
                $payroll = Payroll::create([
                    'employee_id' => $payrollData['employee_id'],
                    'period' => $payrollData['period'],
                    'year' => $year,
                    'month' => $month,
                    'basic' => $payrollData['basic'],
                    'allowances' => $payrollData['allowances'],
                    'deductions' => $payrollData['deductions'],
                    'net' => $payrollData['net'],
                    'status' => $payrollData['status'],
                ]);
                $processed[] = $payroll;
            } catch (\Exception $e) {
                $errors[] = [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'processed' => $processed,
            'errors' => $errors,
            'total' => count($employees),
            'success' => count($processed),
            'failed' => count($errors),
        ];
    }
}
