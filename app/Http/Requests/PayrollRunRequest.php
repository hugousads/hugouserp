<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Payroll;
use Illuminate\Foundation\Http\FormRequest;

class PayrollRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hrm.payroll.run');
    }

    public function rules(): array
    {
        return [
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'payment_date' => ['required', 'date'],
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['exists:hr_employees,id'],
            'include_overtime' => ['boolean'],
            'include_deductions' => ['boolean'],
            'include_bonuses' => ['boolean'],
            'notes' => ['nullable', 'string'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check for duplicate payroll for any employee
            if ($this->has('employee_ids') && $this->has('year') && $this->has('month')) {
                $year = (int) $this->input('year');
                $month = (int) $this->input('month');
                $employeeIds = $this->input('employee_ids', []);

                foreach ($employeeIds as $employeeId) {
                    $existingPayroll = Payroll::where('employee_id', $employeeId)
                        ->where('year', $year)
                        ->where('month', $month)
                        ->first();

                    if ($existingPayroll) {
                        $validator->errors()->add(
                            'employee_ids',
                            __('Payroll already exists for employee ID :id in :month/:year', [
                                'id' => $employeeId,
                                'month' => $month,
                                'year' => $year,
                            ])
                        );
                    }
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('branch_id') && $this->user()->branch_id) {
            $this->merge([
                'branch_id' => $this->user()->branch_id,
            ]);
        }

        if (! $this->has('include_overtime')) {
            $this->merge([
                'include_overtime' => true,
            ]);
        }

        if (! $this->has('include_deductions')) {
            $this->merge([
                'include_deductions' => true,
            ]);
        }

        if (! $this->has('include_bonuses')) {
            $this->merge([
                'include_bonuses' => true,
            ]);
        }
    }
}
