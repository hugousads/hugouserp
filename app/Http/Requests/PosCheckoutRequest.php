<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class PosCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0', 'lte:999999'],
            'items.*.price' => ['sometimes', 'numeric', 'gte:0'],
            'items.*.discount' => ['sometimes', 'numeric', 'gte:0'],
            'items.*.percent' => ['sometimes', 'boolean'],
            'items.*.tax_id' => ['sometimes', 'integer', 'exists:taxes,id'],
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
            'warehouse_id' => ['sometimes', 'integer', 'exists:warehouses,id'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError($validator->errors()->toArray())
        );
    }
}
