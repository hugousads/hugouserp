<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\SaleCompleted;
use App\Models\StockMovement;
use App\Repositories\Contracts\StockMovementRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class UpdateStockOnSale implements ShouldQueue
{
    public function __construct(
        private readonly StockMovementRepositoryInterface $stockMovementRepo
    ) {}

    public function handle(SaleCompleted $event): void
    {
        $sale = $event->sale;
        $warehouseId = $sale->warehouse_id;

        foreach ($sale->items as $item) {
            // BUG FIX #2: Apply unit of measure conversion factor
            // Load the unit relation to get conversion factor
            $item->load('unit');
            $conversionFactor = $item->unit?->conversion_factor ?? 1.0;
            
            // Calculate actual quantity to deduct in base units
            $baseQuantity = (float) $item->quantity * (float) $conversionFactor;

            // Critical ERP Logic: Check for negative stock
            $allowNegativeStock = (bool) setting('inventory.allow_negative_stock', false);

            if (! $allowNegativeStock) {
                $currentStock = \App\Services\StockService::getCurrentStock(
                    $item->product_id,
                    $warehouseId
                );

                if ($currentStock < $baseQuantity) {
                    Log::warning('Insufficient stock for sale', [
                        'sale_id' => $sale->getKey(),
                        'product_id' => $item->product_id,
                        'requested_base_qty' => $baseQuantity,
                        'item_qty' => $item->quantity,
                        'conversion_factor' => $conversionFactor,
                        'available' => $currentStock,
                    ]);

                    throw new InvalidArgumentException(
                        "Insufficient stock for product {$item->product_id}. Available: {$currentStock}, Required: {$baseQuantity}"
                    );
                }
            }

            // Prevent duplicate stock movements for same sale
            $existing = StockMovement::where('reference_type', 'sale')
                ->where('reference_id', $sale->getKey())
                ->where('product_id', $item->product_id)
                ->where('quantity', '<', 0) // Negative quantity = out movement
                ->exists();

            if ($existing) {
                Log::info('Stock movement already recorded for sale', [
                    'sale_id' => $sale->getKey(),
                    'product_id' => $item->product_id,
                ]);

                continue;
            }

            // Use repository for proper schema mapping with base quantity
            $this->stockMovementRepo->create([
                'warehouse_id' => $warehouseId,
                'product_id' => $item->product_id,
                'movement_type' => 'sale',
                'reference_type' => 'sale',
                'reference_id' => $sale->getKey(),
                'qty' => abs($baseQuantity),
                'direction' => 'out',
                'notes' => sprintf('Sale completed (UoM: %s, Factor: %s)', $item->unit?->name ?? 'base', $conversionFactor),
                'created_by' => $sale->created_by,
            ]);
        }
    }
}
