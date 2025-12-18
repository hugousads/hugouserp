<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('products.update') ?? false;
    }

    public function rules(): array
    {
        $product = $this->route('product'); // Model binding

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'sku' => ['sometimes', 'string', 'max:100', 'unique:products,sku,'.$product?->id],
            'barcode' => ['sometimes', 'string', 'max:100', 'unique:products,barcode,'.$product?->id],
            'default_price' => ['sometimes', 'numeric', 'min:0'],
            'cost' => ['sometimes', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:product_categories,id'],
            'tax_id' => ['nullable', 'exists:taxes,id'],
            // Inventory tracking fields
            'min_stock' => ['sometimes', 'numeric', 'min:0'],
            'max_stock' => ['sometimes', 'numeric', 'min:0'],
            'reorder_point' => ['sometimes', 'numeric', 'min:0'],
            'lead_time_days' => ['sometimes', 'numeric', 'min:0'],
            'location_code' => ['sometimes', 'string', 'max:191'],
        ];
    }
}
