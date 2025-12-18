<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaleUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sales.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'customer_notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'internal_notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'delivery_date' => ['sometimes', 'nullable', 'date'],
            'expected_delivery_date' => ['sometimes', 'nullable', 'date'],
            'actual_delivery_date' => ['sometimes', 'nullable', 'date'],
            'shipping_method' => ['sometimes', 'nullable', 'string', 'max:191'],
            'tracking_number' => ['sometimes', 'nullable', 'string', 'max:191'],
            'payment_status' => ['sometimes', 'nullable', 'in:unpaid,partial,paid,overpaid'],
            'payment_due_date' => ['sometimes', 'nullable', 'date'],
            'discount_type' => ['sometimes', 'nullable', 'in:fixed,percentage'],
            'discount_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];
    }
}
