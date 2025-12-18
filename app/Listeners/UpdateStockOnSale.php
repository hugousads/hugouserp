<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\SaleCompleted;
use App\Models\StockMovement;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateStockOnSale implements ShouldQueue
{
    public function handle(SaleCompleted $event): void
    {
        $sale = $event->sale;
        $branchId = $sale->branch_id;
        $warehouseId = $sale->warehouse_id;

        foreach ($sale->items as $item) {
            // Critical ERP Logic: Check for negative stock
            $allowNegativeStock = (bool) setting('inventory.allow_negative_stock', false);
            
            if (!$allowNegativeStock) {
                $currentStock = \App\Services\StockService::getCurrentStock(
                    $item->product_id, 
                    $warehouseId
                );
                
                if ($currentStock < $item->qty) {
                    \Log::warning('Insufficient stock for sale', [
                        'sale_id' => $sale->getKey(),
                        'product_id' => $item->product_id,
                        'requested' => $item->qty,
                        'available' => $currentStock,
                    ]);
                    
                    throw new \Exception(
                        "Insufficient stock for product {$item->product_id}. Available: {$currentStock}, Required: {$item->qty}"
                    );
                }
            }
            
            // Prevent duplicate stock movements for same sale
            $existing = StockMovement::where('reference_type', 'sale')
                ->where('reference_id', $sale->getKey())
                ->where('product_id', $item->product_id)
                ->where('direction', 'out')
                ->exists();
                
            if ($existing) {
                \Log::info('Stock movement already recorded for sale', [
                    'sale_id' => $sale->getKey(),
                    'product_id' => $item->product_id,
                ]);
                continue;
            }

            StockMovement::create([
                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
                'product_id' => $item->product_id,
                'reference_type' => 'sale',
                'reference_id' => $sale->getKey(),
                'qty' => abs((float) $item->qty),
                'direction' => 'out',
                'notes' => 'Sale completed',
                'created_by' => $sale->created_by,
            ]);
        }
    }
}
