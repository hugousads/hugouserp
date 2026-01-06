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
            // Critical ERP Logic: Check for negative stock
            $allowNegativeStock = (bool) setting('inventory.allow_negative_stock', false);
            
            if (!$allowNegativeStock) {
                $currentStock = \App\Services\StockService::getCurrentStock(
                    $item->product_id, 
                    $warehouseId
                );
                
                // Use backward compatibility accessor (qty maps to quantity)
                $requiredQty = (float) $item->quantity;
                
                if ($currentStock < $requiredQty) {
                    Log::warning('Insufficient stock for sale', [
                        'sale_id' => $sale->getKey(),
                        'product_id' => $item->product_id,
                        'requested' => $requiredQty,
                        'available' => $currentStock,
                    ]);
                    
                    throw new InvalidArgumentException(
                        "Insufficient stock for product {$item->product_id}. Available: {$currentStock}, Required: {$requiredQty}"
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

            // Use repository for proper schema mapping
            $this->stockMovementRepo->create([
                'warehouse_id' => $warehouseId,
                'product_id' => $item->product_id,
                'movement_type' => 'sale',
                'reference_type' => 'sale',
                'reference_id' => $sale->getKey(),
                'qty' => abs((float) $item->quantity),
                'direction' => 'out',
                'notes' => 'Sale completed',
                'created_by' => $sale->created_by,
            ]);
        }
    }
}
