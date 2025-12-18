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
            // Inventory fields
            'min_stock' => (float) $this->min_stock,
            'max_stock' => (float) $this->max_stock,
            'reorder_point' => (float) $this->reorder_point,
            'reorder_qty' => (float) $this->reorder_qty,
            'lead_time_days' => $this->lead_time_days ? (float) $this->lead_time_days : null,
            'location_code' => $this->location_code,
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
