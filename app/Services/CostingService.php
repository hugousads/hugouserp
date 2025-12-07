<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InventoryBatch;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Service for handling inventory costing methods
 * Supports FIFO, LIFO, and Weighted Average costing
 */
class CostingService
{
    /**
     * Calculate cost for stock movement based on product's costing method
     */
    public function calculateCost(
        Product $product,
        int $warehouseId,
        float $quantity
    ): array {
        $costMethod = $product->cost_method ?? 'weighted_average';

        return match ($costMethod) {
            'fifo' => $this->calculateFifoCost($product->id, $warehouseId, $quantity),
            'lifo' => $this->calculateLifoCost($product->id, $warehouseId, $quantity),
            'weighted_average' => $this->calculateWeightedAverageCost($product->id, $warehouseId, $quantity),
            'standard' => $this->calculateStandardCost($product, $quantity),
            default => $this->calculateWeightedAverageCost($product->id, $warehouseId, $quantity),
        };
    }

    /**
     * FIFO: First In, First Out
     * Uses the cost of the oldest batches first
     */
    protected function calculateFifoCost(int $productId, int $warehouseId, float $quantity): array
    {
        $batches = InventoryBatch::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->active()
            ->orderBy('created_at', 'asc')
            ->get();

        return $this->allocateCostFromBatches($batches, $quantity);
    }

    /**
     * LIFO: Last In, First Out
     * Uses the cost of the newest batches first
     */
    protected function calculateLifoCost(int $productId, int $warehouseId, float $quantity): array
    {
        $batches = InventoryBatch::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->active()
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->allocateCostFromBatches($batches, $quantity);
    }

    /**
     * Weighted Average: Calculate average cost across all batches
     */
    protected function calculateWeightedAverageCost(int $productId, int $warehouseId, float $quantity): array
    {
        $result = InventoryBatch::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->active()
            ->selectRaw('SUM(quantity * unit_cost) as total_value, SUM(quantity) as total_quantity')
            ->first();

        $totalQuantity = (float) ($result->total_quantity ?? 0);
        $totalValue = (float) ($result->total_value ?? 0);

        if ($totalQuantity <= 0) {
            return [
                'unit_cost' => 0.0,
                'total_cost' => 0.0,
                'batches_used' => [],
            ];
        }

        $avgCost = $totalValue / $totalQuantity;

        return [
            'unit_cost' => $avgCost,
            'total_cost' => $avgCost * $quantity,
            'batches_used' => [],
        ];
    }

    /**
     * Standard Cost: Use the product's standard cost
     */
    protected function calculateStandardCost(Product $product, float $quantity): array
    {
        $unitCost = (float) $product->standard_cost;

        return [
            'unit_cost' => $unitCost,
            'total_cost' => $unitCost * $quantity,
            'batches_used' => [],
        ];
    }

    /**
     * Allocate cost from batches based on order (FIFO/LIFO)
     */
    protected function allocateCostFromBatches($batches, float $quantityNeeded): array
    {
        $totalCost = 0.0;
        $remainingQty = $quantityNeeded;
        $batchesUsed = [];

        foreach ($batches as $batch) {
            if ($remainingQty <= 0) {
                break;
            }

            $batchQty = min($remainingQty, (float) $batch->quantity);
            $batchCost = $batchQty * (float) $batch->unit_cost;

            $totalCost += $batchCost;
            $remainingQty -= $batchQty;

            $batchesUsed[] = [
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'quantity' => $batchQty,
                'unit_cost' => (float) $batch->unit_cost,
                'total_cost' => $batchCost,
            ];
        }

        $unitCost = $quantityNeeded > 0 ? $totalCost / $quantityNeeded : 0.0;

        return [
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
            'batches_used' => $batchesUsed,
        ];
    }

    /**
     * Update batch quantities after stock movement
     */
    public function consumeBatches(array $batchesUsed): void
    {
        DB::transaction(function () use ($batchesUsed) {
            foreach ($batchesUsed as $batchInfo) {
                $batch = InventoryBatch::lockForUpdate()->find($batchInfo['batch_id']);
                if ($batch) {
                    $newQuantity = $batch->quantity - $batchInfo['quantity'];
                    $batch->quantity = max(0, $newQuantity);
                    
                    if ($batch->quantity <= 0) {
                        $batch->status = 'depleted';
                    }
                    
                    $batch->save();
                }
            }
        });
    }

    /**
     * Create or update batch for incoming stock
     */
    public function addToBatch(
        int $productId,
        int $warehouseId,
        int $branchId,
        float $quantity,
        float $unitCost,
        ?string $batchNumber = null,
        ?array $batchData = []
    ): InventoryBatch {
        if (!$batchNumber) {
            $batchNumber = 'BATCH-' . date('Ymd') . '-' . uniqid();
        }

        $batch = InventoryBatch::firstOrNew([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'batch_number' => $batchNumber,
        ]);
        
        if ($batch->exists) {
            // Update existing batch - increment quantity
            $batch->quantity = $batch->quantity + $quantity;
        } else {
            // New batch
            $batch->fill(array_merge([
                'branch_id' => $branchId,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'status' => 'active',
            ], $batchData));
        }
        
        $batch->save();
        return $batch;
    }
}
