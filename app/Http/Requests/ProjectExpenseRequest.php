<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Traits\HasMultilingualValidation;
use Illuminate\Foundation\Http\FormRequest;

class ProjectExpenseRequest extends FormRequest
{
    use HasMultilingualValidation;

    public function authorize(): bool
    {
        return $this->user()->can('projects.expenses.manage');
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'exists:projects,id'],
            'expense_date' => ['required', 'date'],
            'category' => $this->multilingualString(required: true, max: 100),
            'description' => $this->unicodeText(required: true),
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:3'],
            'vendor' => $this->multilingualString(required: false, max: 255),
            'receipt_number' => ['nullable', 'string', 'max:100'],
            'payment_method' => ['nullable', 'in:cash,bank_transfer,credit_card,check'],
            'status' => ['required', 'in:pending,approved,rejected,paid'],
            'notes' => $this->unicodeText(required: false),
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('status')) {
            $this->merge([
                'status' => 'pending',
            ]);
        }
    }
}
