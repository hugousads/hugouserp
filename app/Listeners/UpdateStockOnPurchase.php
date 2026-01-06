<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PurchaseReceived;
use App\Models\StockMovement;
use App\Repositories\Contracts\StockMovementRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class UpdateStockOnPurchase implements ShouldQueue
{
    public function __construct(
        private readonly StockMovementRepositoryInterface $stockMovementRepo
    ) {}

    public function handle(PurchaseReceived $event): void
    {
        $purchase = $event->purchase;
        $warehouseId = $purchase->warehouse_id;

        foreach ($purchase->items as $item) {
            // Critical ERP Logic: Prevent duplicate stock movements
            $existing = StockMovement::where('reference_type', 'purchase')
                ->where('reference_id', $purchase->getKey())
                ->where('product_id', $item->product_id)
                ->where('quantity', '>', 0) // Positive quantity = in movement
                ->exists();
                
            if ($existing) {
                Log::info('Stock movement already recorded for purchase', [
                    'purchase_id' => $purchase->getKey(),
                    'product_id' => $item->product_id,
                ]);
                continue;
            }
            
            // Validate quantity is positive (use quantity column)
            $itemQty = (float) $item->quantity;
            if ($itemQty <= 0) {
                Log::error('Invalid purchase quantity', [
                    'purchase_id' => $purchase->getKey(),
                    'product_id' => $item->product_id,
                    'qty' => $itemQty,
                ]);
                throw new InvalidArgumentException("Purchase quantity must be positive for product {$item->product_id}");
            }

            // Use repository for proper schema mapping
            $this->stockMovementRepo->create([
                'warehouse_id' => $warehouseId,
                'product_id' => $item->product_id,
                'movement_type' => 'purchase',
                'reference_type' => 'purchase',
                'reference_id' => $purchase->getKey(),
                'qty' => $itemQty,
                'direction' => 'in',
                'unit_cost' => $item->unit_price ?? null,
                'notes' => 'Purchase received',
                'created_by' => $purchase->created_by,
            ]);
        }
    }
}
