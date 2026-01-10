<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Traits\HasMultilingualValidation;
use App\Http\Requests\Traits\HasPaymentTermsValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerStoreRequest extends FormRequest
{
    use HasMultilingualValidation;
    use HasPaymentTermsValidation;

    public function authorize(): bool
    {
        return $this->user()?->can('customers.create') ?? false;
    }

    public function rules(): array
    {
        return array_merge([
            'name' => $this->multilingualString(required: true, max: 255),
            'phone' => ['nullable', 'string', 'max:100'],
            'email' => [
                'nullable',
                'email',
                'max:190',
                Rule::unique('customers', 'email')->whereNull('deleted_at'),
            ],
            'billing_address' => $this->unicodeText(required: false, max: 500),
            'shipping_address' => $this->unicodeText(required: false, max: 500),
            'tax_number' => ['nullable', 'string', 'max:50'],
            // Financial fields
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payment_due_days' => ['nullable', 'integer', 'min:0'],
            'preferred_currency' => ['nullable', 'string', 'size:3'],
            'notes' => $this->unicodeText(required: false),
        ],
            $this->paymentTermsRules(),
            $this->paymentDueDaysRules()
        );
    }
}
