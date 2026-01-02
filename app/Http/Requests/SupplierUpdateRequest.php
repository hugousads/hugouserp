<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Traits\HasPaymentTermsValidation;
use Illuminate\Foundation\Http\FormRequest;

class SupplierUpdateRequest extends FormRequest
{
    use HasPaymentTermsValidation;

    public function authorize(): bool
    {
        return $this->user()?->can('suppliers.update') ?? false;
    }

    public function rules(): array
    {
        $supplier = $this->route('supplier'); // Model binding

        return array_merge([
            'name' => ['sometimes', 'string', 'max:255'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'nullable', 'email', 'max:190', 'unique:suppliers,email,'.$supplier?->id],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'tax_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'contact_person' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            // Financial fields
            'minimum_order_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            // Rating fields
            'supplier_rating' => ['sometimes', 'nullable', 'string', 'max:191'],
            'quality_rating' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:5'],
            'delivery_rating' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:5'],
            'service_rating' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:5'],
        ], 
        $this->paymentTermsRules(false),
        $this->paymentDueDaysRules(false)
        );
    }
}
