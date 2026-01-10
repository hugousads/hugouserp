<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * BUG FIX #4: Cache permission check result to avoid N+1 queries
     * Note: Static cache is reset between requests in PHP-FPM/CLI environments
     * Laravel's request lifecycle ensures this doesn't leak between users
     */
    private static ?bool $canViewCost = null;

    /**
     * Track the current request to prevent cache poisoning
     */
    private static ?string $requestId = null;

    public function toArray(Request $request): array
    {
        // BUG FIX #4: Check permission once per request, not per product
        // Reset cache if this is a different request (safety measure)
        $currentRequestId = spl_object_hash($request);
        if (self::$requestId !== $currentRequestId) {
            self::$canViewCost = null;
            self::$requestId = $currentRequestId;
        }

        if (self::$canViewCost === null) {
            self::$canViewCost = $request->user()?->can('products.view-cost') ?? false;
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'notes' => $this->notes,
            'category' => $this->category,
            'brand' => $this->brand,
            'uom' => $this->uom,
            'price' => (float) $this->default_price,
            'cost' => $this->when(self::$canViewCost, (float) $this->cost),
            // Inventory fields
            'min_stock' => $this->min_stock ? (float) $this->min_stock : 0.0,
            'max_stock' => $this->max_stock ? (float) $this->max_stock : null,
            'reorder_point' => $this->reorder_point ? (float) $this->reorder_point : 0.0,
            'reorder_qty' => $this->reorder_qty ? (float) $this->reorder_qty : 0.0,
            'lead_time_days' => $this->lead_time_days ? (float) $this->lead_time_days : null,
            'location_code' => $this->location_code,
            'status' => $this->status,
            'is_service' => $this->product_type === 'service' || $this->type === 'service',
            'tax_id' => $this->tax_id,
            'branch_id' => $this->branch_id,
            'branch' => $this->whenLoaded('branch', fn () => new BranchResource($this->branch)),
            'images' => $this->gallery ?? [],
            'gallery' => $this->gallery ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Reset the cached permission check (useful for testing)
     */
    public static function resetPermissionCache(): void
    {
        self::$canViewCost = null;
        self::$requestId = null;
    }
}
