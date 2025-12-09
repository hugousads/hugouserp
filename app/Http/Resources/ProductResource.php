<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'description' => $this->description,
            'category' => $this->category,
            'brand' => $this->brand,
            'unit' => $this->unit,
            'price' => (float) $this->default_price,
            'cost' => $this->when($request->user()?->can('products.view-cost'), (float) $this->cost),
            'min_stock' => (int) $this->min_stock,
            'is_active' => $this->is_active,
            'is_service' => $this->is_service,
            'tax_rate' => (float) $this->tax_rate,
            'branch_id' => $this->branch_id,
            'branch' => $this->whenLoaded('branch', fn () => new BranchResource($this->branch)),
            'images' => $this->gallery ?? [],
            'gallery' => $this->gallery ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
