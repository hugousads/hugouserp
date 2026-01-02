<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Traits\HasMultilingualValidation;
use Illuminate\Foundation\Http\FormRequest;

class LeaveRequestFormRequest extends FormRequest
{
    use HasMultilingualValidation;

    public function authorize(): bool
    {
        return true; // Employees can create their own leave requests
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:hr_employees,id'],
            'leave_type' => ['required', 'in:annual,sick,emergency,unpaid,maternity,paternity'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => $this->unicodeText(required: true, min: 10),
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'], // 5MB max
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.after_or_equal' => __('Leave start date must be today or in the future'),
            'end_date.after_or_equal' => __('Leave end date must be after or equal to start date'),
            'reason.min' => __('Please provide a detailed reason (at least 10 characters)'),
        ];
    }
}
