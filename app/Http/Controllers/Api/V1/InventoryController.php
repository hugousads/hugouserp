<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\Inventory\BulkUpdateStockRequest;
use App\Http\Requests\Api\Inventory\GetMovementsRequest;
use App\Http\Requests\Api\Inventory\GetStockRequest;
use App\Http\Requests\Api\Inventory\UpdateStockRequest;
use App\Models\Product;
use App\Models\ProductStoreMapping;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryController extends BaseApiController
{
    public function getStock(GetStockRequest $request): JsonResponse
    {
        $store = $this->getStore($request);
        $validated = $request->validated();

        $query = Product::query()
            ->select([
                'products.id',
                'products.name',
                'products.sku',
                'products.min_stock',
                'products.branch_id',
                DB::raw('COALESCE(SUM(CASE WHEN stock_movements.direction = ? THEN stock_movements.qty ELSE 0 END) - SUM(CASE WHEN stock_movements.direction = ? THEN stock_movements.qty ELSE 0 END), 0) as current_quantity'),
            ])
            ->addBinding(['in', 'out'], 'select')
            ->leftJoin('stock_movements', 'products.id', '=', 'stock_movements.product_id')
            ->when($store?->branch_id, fn ($q) => $q->where('products.branch_id', $store->branch_id))
            ->when($request->filled('sku'), fn ($q) => $q->where('products.sku', $validated['sku']))
            ->when($request->filled('warehouse_id'), function ($q) use ($validated) {
                $q->where('stock_movements.warehouse_id', $validated['warehouse_id'])
                    ->addSelect('stock_movements.warehouse_id')
                    ->groupBy('stock_movements.warehouse_id');
            })
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.min_stock', 'products.branch_id');

        // For low stock filter
        if ($request->boolean('low_stock')) {
            $query->havingRaw('current_quantity <= products.min_stock');
        }

        $products = $query->paginate($validated['per_page'] ?? 100);

        return $this->paginatedResponse($products, __('Stock levels retrieved successfully'));
    }

    public function updateStock(UpdateStockRequest $request): JsonResponse
    {
        $validated = $request->validated();
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

        $warehouseId = $this->resolveWarehouseId(
            $request->input('warehouse_id'),
            $product->branch_id,
            $store?->branch_id
        );

        if ($warehouseId === null) {
            throw ValidationException::withMessages([
                'warehouse_id' => [__('No warehouse available for stock movement')]
            ]);
        }

        $oldQuantity = $this->calculateCurrentStock($product->id, $warehouseId, $product->branch_id);

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

        $newQuantityPersisted = DB::transaction(function () use ($product, $actualDirection, $actualQty, $validated, $warehouseId) {
            if ($actualQty > 0) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouseId,
                    'branch_id' => $product->branch_id,
                    'direction' => $actualDirection,
                    'qty' => $actualQty,
                    'reason' => $validated['reason'] ?? 'API stock update',
                    'reference_type' => 'api_sync',
                ]);
            }

            $freshProduct = Product::lockForUpdate()->find($product->id);
            if (! $freshProduct) {
                throw new \RuntimeException('Product not found during stock update');
            }
            $calculated = $this->calculateCurrentStock($product->id, null, $product->branch_id);
            $freshProduct->forceFill(['stock_quantity' => max(0, $calculated)])->save();

            return max(0, $calculated);
        });

        return $this->successResponse([
            'product_id' => $product->id,
            'sku' => $product->sku,
            'old_quantity' => $oldQuantity,
            'new_quantity' => $newQuantityPersisted,
        ], __('Stock updated successfully'));
    }

    public function bulkUpdateStock(BulkUpdateStockRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $store = $this->getStore($request);
        $results = [
            'success' => [],
            'failed' => [],
        ];

        foreach ($validated['updates'] as $item) {
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
                $warehouseId = $this->resolveWarehouseId(
                    $item['warehouse_id'] ?? $request->input('warehouse_id'),
                    $product->branch_id,
                    $store?->branch_id
                );

                // If warehouse cannot be resolved, record failure and continue
                if ($warehouseId === null) {
                    $results['failed'][] = [
                        'identifier' => $item['product_id'] ?? $item['external_id'],
                        'error' => __('No warehouse available for stock movement'),
                    ];
                    continue;
                }

                // Get current quantity using helper method with warehouse and branch scoping
                $oldQuantity = $this->calculateCurrentStock($product->id, $warehouseId, $product->branch_id);

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

                $persistedQuantity = DB::transaction(function () use ($product, $actualDirection, $actualQty, $warehouseId) {
                    if ($actualQty > 0) {
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

                    $freshProduct = Product::lockForUpdate()->find($product->id);
                    if (! $freshProduct) {
                        throw new \RuntimeException('Product not found during stock update');
                    }
                    $calculated = $this->calculateCurrentStock($product->id, null, $product->branch_id);
                    $freshProduct->forceFill(['stock_quantity' => max(0, $calculated)])->save();

                    return max(0, $calculated);
                });

                $results['success'][] = [
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'old_quantity' => $oldQuantity,
                    'new_quantity' => $persistedQuantity,
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

    public function getMovements(GetMovementsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $store = $this->getStore($request);

        $query = StockMovement::query()
            ->with(['product:id,name,sku'])
            ->when($store?->branch_id, fn ($q) => $q->where('branch_id', $store->branch_id))
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $validated['product_id']))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $validated['warehouse_id']))
            ->when($request->filled('direction'), fn ($q) => $q->where('direction', $validated['direction']))
            ->when($request->filled('start_date'), fn ($q) => $q->whereDate('created_at', '>=', $validated['start_date']))
            ->when($request->filled('end_date'), fn ($q) => $q->whereDate('created_at', '<=', $validated['end_date']))
            ->orderBy('created_at', 'desc');

        $movements = $query->paginate($validated['per_page'] ?? 50);

        return $this->paginatedResponse($movements, __('Stock movements retrieved successfully'));
    }

    /**
     * Calculate current stock quantity for a product
     * 
     * @param int $productId Product ID
     * @param int|null $warehouseId Optional warehouse ID filter
     * @param int|null $branchId Optional branch ID filter
     * @return float Current stock balance
     */
    protected function calculateCurrentStock(int $productId, ?int $warehouseId = null, ?int $branchId = null): float
    {
        $query = StockMovement::where('product_id', $productId);

        if ($warehouseId !== null) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        return (float) ($query->selectRaw('SUM(CASE WHEN direction = "in" THEN qty ELSE 0 END) - SUM(CASE WHEN direction = "out" THEN qty ELSE 0 END) as balance')
            ->value('balance') ?? 0);
    }

    /**
     * Resolve warehouse ID with branch-safe fallback logic.
     * Priority: preferred ID → default setting → branch warehouse.
     *
     * @param  int|null  $preferredId  Preferred warehouse ID from request
     * @param  int|null  $branchId  Branch ID to filter warehouses
     * @param  int|null  $tokenBranchId  Branch ID from token/store context
     * @return int|null Resolved warehouse ID or null if none available
     */
    protected function resolveWarehouseId(?int $preferredId, ?int $branchId = null, ?int $tokenBranchId = null): ?int
    {
        $branchContext = $branchId ?? $tokenBranchId;

        if ($preferredId !== null) {
            $warehouse = Warehouse::query()
                ->where('id', $preferredId)
                ->when($branchContext, fn ($q, $branch) => $q->where('branch_id', $branch))
                ->where('status', 'active')
                ->first();

            if (! $warehouse) {
                throw ValidationException::withMessages([
                    'warehouse_id' => [__('Invalid warehouse for this branch')],
                ]);
            }

            return $warehouse->id;
        }

        // Try default warehouse from settings scoped to branch if provided
        $defaultWarehouseId = setting('default_warehouse_id');
        if ($defaultWarehouseId !== null) {
            $defaultWarehouse = Warehouse::query()
                ->where('id', $defaultWarehouseId)
                ->where('status', 'active')
                ->when($branchContext, fn ($q, $branch) => $q->where('branch_id', $branch))
                ->first();

            if ($defaultWarehouse) {
                return (int) $defaultWarehouse->id;
            }
        }

        // Try to get warehouse from branch context
        if ($branchContext !== null) {
            $branchWarehouse = \App\Models\Warehouse::where('branch_id', $branchContext)
                ->where('status', 'active')
                ->first();

            if ($branchWarehouse) {
                return $branchWarehouse->id;
            }

            // Do not fall back to a warehouse from another branch
            return null;
        }

        // Fall back to first available active warehouse when no branch context is provided
        $firstWarehouse = \App\Models\Warehouse::where('status', 'active')->first();

        return $firstWarehouse?->id;
    }
}
