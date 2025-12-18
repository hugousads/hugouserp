<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PurchaseReceived;
use App\Models\StockMovement;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateStockOnPurchase implements ShouldQueue
{
    public function handle(PurchaseReceived $event): void
    {
        $purchase = $event->purchase;
        $branchId = $purchase->branch_id;
        $warehouseId = $purchase->warehouse_id;

        foreach ($purchase->items as $item) {
            // Critical ERP Logic: Prevent duplicate stock movements
            $existing = StockMovement::where('reference_type', 'purchase')
                ->where('reference_id', $purchase->getKey())
                ->where('product_id', $item->product_id)
                ->where('direction', 'in')
                ->exists();
                
            if ($existing) {
                \Log::info('Stock movement already recorded for purchase', [
                    'purchase_id' => $purchase->getKey(),
                    'product_id' => $item->product_id,
                ]);
                continue;
            }
            
            // Validate quantity is positive
            if ($item->qty <= 0) {
                \Log::error('Invalid purchase quantity', [
                    'purchase_id' => $purchase->getKey(),
                    'product_id' => $item->product_id,
                    'qty' => $item->qty,
                ]);
                throw new \Exception("Purchase quantity must be positive for product {$item->product_id}");
            }

            StockMovement::create([
                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
                'product_id' => $item->product_id,
                'reference_type' => 'purchase',
                'reference_id' => $purchase->getKey(),
                'qty' => $item->qty,
                'direction' => 'in',
                'notes' => 'Purchase received',
                'created_by' => $purchase->created_by,
            ]);
        }
    }
}
