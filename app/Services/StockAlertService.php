<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LowStockAlert;
use App\Models\Product;
use App\Models\StockMovement;
use App\Traits\HandlesServiceErrors;

class StockAlertService
{
    use HandlesServiceErrors;

    public function checkProductStock(Product $product, ?int $warehouseId = null): ?LowStockAlert
    {
        return $this->handleServiceOperation(
            callback: function () use ($product, $warehouseId) {
                if (! $product->track_stock_alerts || $product->min_stock <= 0) {
                    return null;
                }

                $currentQty = $this->getCurrentStock($product, $warehouseId);

                if ($currentQty >= $product->min_stock) {
                    $this->resolveExistingAlerts($product, $warehouseId);

                    return null;
                }

                $existingAlert = LowStockAlert::where('product_id', $product->id)
                    ->where('warehouse_id', $warehouseId)
                    ->whereIn('status', ['active', 'acknowledged'])
                    ->first();

                if ($existingAlert) {
                    $existingAlert->update(['current_stock' => $currentQty]);

                    return $existingAlert;
                }

                return LowStockAlert::create([
                    'product_id' => $product->id,
                    'branch_id' => $product->branch_id,
                    'warehouse_id' => $warehouseId,
                    'current_stock' => $currentQty,
                    'alert_threshold' => $product->min_stock,
                    'status' => 'active',
                ]);
            },
            operation: 'checkProductStock',
            context: ['product_id' => $product->id, 'warehouse_id' => $warehouseId],
            defaultValue: null
        );
    }

    public function checkAllProducts(?int $branchId = null): array
    {
        return $this->handleServiceOperation(
            callback: function () use ($branchId) {
                $alerts = [];

                $query = Product::where('track_stock_alerts', true)
                    ->where('min_stock', '>', 0);

                if ($branchId) {
                    $query->where('branch_id', $branchId);
                }

                foreach ($query->cursor() as $product) {
                    $alert = $this->checkProductStock($product);
                    if ($alert) {
                        $alerts[] = $alert;
                    }
                }

                return $alerts;
            },
            operation: 'checkAllProducts',
            context: ['branch_id' => $branchId],
            defaultValue: []
        );
    }

    public function getActiveAlerts(?int $branchId = null, ?int $warehouseId = null): \Illuminate\Database\Eloquent\Collection
    {
        return $this->handleServiceOperation(
            callback: fn () => LowStockAlert::with(['product', 'warehouse', 'branch'])
                ->active()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
                ->orderByDesc('created_at')
                ->get(),
            operation: 'getActiveAlerts',
            context: ['branch_id' => $branchId, 'warehouse_id' => $warehouseId]
        );
    }

    public function getUnresolvedAlerts(?int $branchId = null): \Illuminate\Database\Eloquent\Collection
    {
        return $this->handleServiceOperation(
            callback: fn () => LowStockAlert::with(['product', 'warehouse', 'branch'])
                ->unresolved()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->orderByDesc('created_at')
                ->get(),
            operation: 'getUnresolvedAlerts',
            context: ['branch_id' => $branchId]
        );
    }

    public function acknowledgeAlert(LowStockAlert $alert, int $userId): void
    {
        $this->handleServiceOperation(
            callback: fn () => $alert->acknowledge($userId),
            operation: 'acknowledgeAlert',
            context: ['alert_id' => $alert->id, 'user_id' => $userId]
        );
    }

    public function resolveAlert(LowStockAlert $alert, int $userId): void
    {
        $this->handleServiceOperation(
            callback: fn () => $alert->resolve($userId),
            operation: 'resolveAlert',
            context: ['alert_id' => $alert->id, 'user_id' => $userId]
        );
    }

    public function getAlertStats(?int $branchId = null): array
    {
        return $this->handleServiceOperation(
            callback: function () use ($branchId) {
                $query = LowStockAlert::query()
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

                return [
                    'total_active' => (clone $query)->where('status', 'active')->count(),
                    'total_acknowledged' => (clone $query)->where('status', 'acknowledged')->count(),
                    'total_resolved_today' => (clone $query)
                        ->where('status', 'resolved')
                        ->whereDate('resolved_at', today())
                        ->count(),
                    'critical_count' => (clone $query)
                        ->where('status', 'active')
                        ->whereRaw('current_stock <= alert_threshold * 0.25')
                        ->count(),
                ];
            },
            operation: 'getAlertStats',
            context: ['branch_id' => $branchId]
        );
    }

    protected function getCurrentStock(Product $product, ?int $warehouseId = null): int
    {
        $query = StockMovement::where('product_id', $product->id);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        // quantity is signed: positive = in, negative = out
        return (int) $query->sum('quantity');
    }

    protected function resolveExistingAlerts(Product $product, ?int $warehouseId = null): void
    {
        LowStockAlert::where('product_id', $product->id)
            ->where('warehouse_id', $warehouseId)
            ->whereIn('status', ['active', 'acknowledged'])
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
            ]);
    }
}
