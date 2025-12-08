<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Product;
use App\Models\ProductStoreMapping;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends BaseApiController
{
    public function getStock(Request $request): JsonResponse
    {
        $store = $this->getStore($request);

        $query = Product::query()
            ->select([
                'products.id',
                'products.name',
                'products.sku',
                'products.min_stock',
                'products.branch_id',
                DB::raw('COALESCE(SUM(CASE WHEN stock_movements.direction = "in" THEN stock_movements.qty ELSE 0 END) - SUM(CASE WHEN stock_movements.direction = "out" THEN stock_movements.qty ELSE 0 END), 0) as current_quantity'),
            ])
            ->leftJoin('stock_movements', 'products.id', '=', 'stock_movements.product_id')
            ->when($store?->branch_id, fn ($q) => $q->where('products.branch_id', $store->branch_id))
            ->when($request->filled('sku'), fn ($q) => $q->where('products.sku', $request->sku))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('stock_movements.warehouse_id', $request->warehouse_id))
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.min_stock', 'products.branch_id');

        // For low stock filter
        if ($request->boolean('low_stock')) {
            $query->havingRaw('current_quantity <= products.min_stock');
        }

        $products = $query->paginate($request->get('per_page', 100));

        return $this->paginatedResponse($products, __('Stock levels retrieved successfully'));
    }

    public function updateStock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required_without:external_id|exists:products,id',
            'external_id' => 'required_without:product_id|string',
            'qty' => 'required|numeric',
            'direction' => 'required|in:in,out,set',
            'reason' => 'nullable|string|max:255',
        ]);

        $store = $this->getStore($request);

        $product = null;

        if ($request->filled('product_id')) {
            $product = Product::query()
                ->when($store?->branch_id, fn ($q) => $q->where('branch_id', $store->branch_id))
                ->find($validated['product_id']);
        } elseif ($request->filled('external_id') && $store) {
            $mapping = ProductStoreMapping::where('store_id', $store->id)
                ->where('external_id', $validated['external_id'])
                ->first();

            if ($mapping) {
                $product = $mapping->product;
            }
        }

        if (! $product) {
            return $this->errorResponse(__('Product not found'), 404);
        }

        // Get current quantity using helper method
        $oldQuantity = $this->calculateCurrentStock($product->id);

        // Calculate new quantity and direction
        if ($validated['direction'] === 'set') {
            $newQuantity = (float) $validated['qty'];
            $difference = $newQuantity - $oldQuantity;
            $actualDirection = $difference >= 0 ? 'in' : 'out';
            $actualQty = abs($difference);
        } else {
            $actualDirection = $validated['direction'];
            $actualQty = abs((float) $validated['qty']);
            $newQuantity = $actualDirection === 'in'
                ? $oldQuantity + $actualQty
                : $oldQuantity - $actualQty;
        }

        if ($actualQty > 0) {
            DB::transaction(function () use ($product, $actualDirection, $actualQty, $validated, $request) {
                // Get warehouse_id from request, settings, or first available warehouse
                $warehouseId = $request->input('warehouse_id')
                    ?? settings('default_warehouse_id')
                    ?? \App\Models\Warehouse::first()?->id;

                StockMovement::create([
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouseId,
                    'branch_id' => $product->branch_id,
                    'direction' => $actualDirection,
                    'qty' => $actualQty,
                    'reason' => $validated['reason'] ?? 'API stock update',
                    'reference_type' => 'api_sync',
                ]);
            });
        }

        return $this->successResponse([
            'product_id' => $product->id,
            'sku' => $product->sku,
            'old_quantity' => $oldQuantity,
            'new_quantity' => max(0, $newQuantity),
        ], __('Stock updated successfully'));
    }

    public function bulkUpdateStock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1|max:100',
            'items.*.product_id' => 'required_without:items.*.external_id|exists:products,id',
            'items.*.external_id' => 'required_without:items.*.product_id|string',
            'items.*.qty' => 'required|numeric',
            'items.*.direction' => 'required|in:in,out,set',
        ]);

        $store = $this->getStore($request);
        $results = [
            'success' => [],
            'failed' => [],
        ];

        foreach ($validated['items'] as $item) {
            $product = null;

            if (isset($item['product_id'])) {
                $product = Product::query()
                    ->when($store?->branch_id, fn ($q) => $q->where('branch_id', $store->branch_id))
                    ->find($item['product_id']);
            } elseif (isset($item['external_id']) && $store) {
                $mapping = ProductStoreMapping::where('store_id', $store->id)
                    ->where('external_id', $item['external_id'])
                    ->first();

                if ($mapping) {
                    $product = $mapping->product;
                }
            }

            if (! $product) {
                $results['failed'][] = [
                    'identifier' => $item['product_id'] ?? $item['external_id'],
                    'error' => __('Product not found'),
                ];

                continue;
            }

            try {
                // Get current quantity using helper method
                $oldQuantity = $this->calculateCurrentStock($product->id);

                // Calculate new quantity and direction
                if ($item['direction'] === 'set') {
                    $newQuantity = (float) $item['qty'];
                    $difference = $newQuantity - $oldQuantity;
                    $actualDirection = $difference >= 0 ? 'in' : 'out';
                    $actualQty = abs($difference);
                } else {
                    $actualDirection = $item['direction'];
                    $actualQty = abs((float) $item['qty']);
                    $newQuantity = $actualDirection === 'in'
                        ? $oldQuantity + $actualQty
                        : $oldQuantity - $actualQty;
                }

                if ($actualQty > 0) {
                    // Get warehouse_id from item, request, settings, or first available warehouse
                    $warehouseId = $item['warehouse_id'] ?? $request->input('warehouse_id')
                        ?? settings('default_warehouse_id')
                        ?? \App\Models\Warehouse::first()?->id;

                    StockMovement::create([
                        'product_id' => $product->id,
                        'warehouse_id' => $warehouseId,
                        'branch_id' => $product->branch_id,
                        'direction' => $actualDirection,
                        'qty' => $actualQty,
                        'reason' => 'API bulk stock update',
                        'reference_type' => 'api_sync',
                    ]);
                }

                $results['success'][] = [
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'old_quantity' => $oldQuantity,
                    'new_quantity' => max(0, $newQuantity),
                ];
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'identifier' => $item['product_id'] ?? $item['external_id'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $this->successResponse($results, __('Bulk stock update completed'));
    }

    public function getMovements(Request $request): JsonResponse
    {
        $store = $this->getStore($request);

        $query = StockMovement::query()
            ->with(['product:id,name,sku'])
            ->when($store?->branch_id, fn ($q) => $q->where('branch_id', $store->branch_id))
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->product_id))
            ->when($request->filled('direction'), fn ($q) => $q->where('direction', $request->direction))
            ->when($request->filled('from_date'), fn ($q) => $q->whereDate('created_at', '>=', $request->from_date))
            ->when($request->filled('to_date'), fn ($q) => $q->whereDate('created_at', '<=', $request->to_date))
            ->orderBy('created_at', 'desc');

        $movements = $query->paginate($request->get('per_page', 50));

        return $this->paginatedResponse($movements, __('Stock movements retrieved successfully'));
    }

    /**
     * Calculate current stock quantity for a product
     */
    protected function calculateCurrentStock(int $productId): float
    {
        return (float) (StockMovement::where('product_id', $productId)
            ->selectRaw('SUM(CASE WHEN direction = "in" THEN qty ELSE 0 END) - SUM(CASE WHEN direction = "out" THEN qty ELSE 0 END) as balance')
            ->value('balance') ?? 0);
    }
}
