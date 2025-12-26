<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Traits\HasPaymentTermsValidation;
use Illuminate\Foundation\Http\FormRequest;

class CustomerUpdateRequest extends FormRequest
{
    use HasPaymentTermsValidation;

    public function authorize(): bool
    {
        return $this->user()?->can('customers.update') ?? false;
    }

    public function rules(): array
    {
        $customer = $this->route('customer'); // Model binding

        return array_merge([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'nullable', 'email', 'max:190', 'unique:customers,email,'.$customer?->id],
            'billing_address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'shipping_address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'tax_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            // Financial fields
            'credit_limit' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'discount_percentage' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'payment_due_days' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'preferred_currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ],
        $this->paymentTermsRules(false),
        $this->paymentDueDaysRules(false)
        );
    }
}
