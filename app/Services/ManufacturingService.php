<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BillOfMaterial;
use App\Models\ProductionOrder;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class ManufacturingService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly AccountingService $accountingService
    ) {}

    /**
     * Create a Bill of Materials.
     */
    public function createBom(array $data): BillOfMaterial
    {
        return DB::transaction(function () use ($data) {
            $bom = BillOfMaterial::create([
                'branch_id' => $data['branch_id'],
                'product_id' => $data['product_id'],
                'bom_number' => $data['bom_number'] ?? BillOfMaterial::generateBomNumber($data['branch_id']),
                'name' => $data['name'],
                'name_ar' => $data['name_ar'] ?? null,
                'description' => $data['description'] ?? null,
                'quantity' => $data['quantity'] ?? 1.00,
                'status' => $data['status'] ?? 'draft',
                'scrap_percentage' => $data['scrap_percentage'] ?? 0.00,
                'is_multi_level' => $data['is_multi_level'] ?? false,
                'metadata' => $data['metadata'] ?? null,
            ]);

            // Add BOM items if provided
            if (! empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    $bom->items()->create($item);
                }
            }

            // Add operations if provided
            if (! empty($data['operations'])) {
                foreach ($data['operations'] as $operation) {
                    $bom->operations()->create($operation);
                }
            }

            return $bom->load(['items.product', 'operations.workCenter']);
        });
    }

    /**
     * Update a BOM.
     */
    public function updateBom(BillOfMaterial $bom, array $data): BillOfMaterial
    {
        return DB::transaction(function () use ($bom, $data) {
            $bom->update(array_filter([
                'name' => $data['name'] ?? null,
                'name_ar' => $data['name_ar'] ?? null,
                'description' => $data['description'] ?? null,
                'quantity' => $data['quantity'] ?? null,
                'status' => $data['status'] ?? null,
                'scrap_percentage' => $data['scrap_percentage'] ?? null,
                'is_multi_level' => $data['is_multi_level'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ], fn ($value) => ! is_null($value)));

            // Update items if provided
            if (isset($data['items'])) {
                $bom->items()->delete();
                foreach ($data['items'] as $item) {
                    // Validate required item fields
                    if (!isset($item['product_id']) || !isset($item['quantity'])) {
                        continue;
                    }
                    $bom->items()->create($item);
                }
            }

            // Update operations if provided
            if (isset($data['operations'])) {
                $bom->operations()->delete();
                foreach ($data['operations'] as $operation) {
                    // Validate required operation fields
                    if (!isset($operation['work_center_id']) || !isset($operation['operation_name'])) {
                        continue;
                    }
                    $bom->operations()->create($operation);
                }
            }

            return $bom->fresh(['items.product', 'operations.workCenter']);
        });
    }

    /**
     * Create a production order.
     */
    public function createProductionOrder(array $data): ProductionOrder
    {
        return DB::transaction(function () use ($data) {
            $bom = BillOfMaterial::findOrFail($data['bom_id']);

            $order = ProductionOrder::create([
                'branch_id' => $data['branch_id'],
                'order_number' => $data['order_number'] ?? ProductionOrder::generateOrderNumber($data['branch_id']),
                'bom_id' => $bom->id,
                'product_id' => $bom->product_id,
                'warehouse_id' => $data['warehouse_id'],
                'quantity_planned' => $data['quantity_planned'],
                'status' => $data['status'] ?? 'draft',
                'priority' => $data['priority'] ?? 'normal',
                'planned_start_date' => $data['planned_start_date'] ?? null,
                'planned_end_date' => $data['planned_end_date'] ?? null,
                'created_by' => $data['created_by'] ?? auth()->id(),
                'notes' => $data['notes'] ?? null,
                'estimated_cost' => $bom->calculateTotalCost() * $data['quantity_planned'],
                'sale_id' => $data['sale_id'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);

            // Create order items from BOM
            foreach ($bom->items as $bomItem) {
                $order->items()->create([
                    'product_id' => $bomItem->product_id,
                    'quantity_required' => $bomItem->effective_quantity * $data['quantity_planned'],
                    'unit_id' => $bomItem->unit_id,
                    'unit_cost' => $bomItem->product->cost ?? 0.00,
                    'total_cost' => ($bomItem->product->cost ?? 0.00) * $bomItem->effective_quantity * $data['quantity_planned'],
                    'warehouse_id' => $data['warehouse_id'],
                ]);
            }

            // Create order operations from BOM
            foreach ($bom->operations as $bomOperation) {
                $order->operations()->create([
                    'bom_operation_id' => $bomOperation->id,
                    'work_center_id' => $bomOperation->work_center_id,
                    'operation_name' => $bomOperation->operation_name,
                    'sequence' => $bomOperation->sequence,
                    'planned_duration_minutes' => $bomOperation->total_time * $data['quantity_planned'],
                ]);
            }

            return $order->load(['items.product', 'operations.workCenter']);
        });
    }

    /**
     * Release production order (make it ready to start).
     */
    public function releaseProductionOrder(ProductionOrder $order): ProductionOrder
    {
        // Check material availability
        foreach ($order->items as $item) {
            $available = $this->inventoryService->getStockLevel(
                $item->product_id,
                $order->warehouse_id
            );

            if ($available < $item->quantity_required) {
                throw new \Exception(
                    "Insufficient stock for {$item->product->name}. Required: {$item->quantity_required}, Available: {$available}"
                );
            }
        }

        $order->update(['status' => 'released']);

        return $order;
    }

    /**
     * Issue materials for production.
     */
    public function issueMaterials(ProductionOrder $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                if ($item->is_issued) {
                    continue;
                }

                // Create stock movement for material issue
                StockMovement::create([
                    'branch_id' => $order->branch_id,
                    'product_id' => $item->product_id,
                    'warehouse_id' => $order->warehouse_id,
                    'direction' => 'out',
                    'qty' => $item->quantity_required,
                    'reference_type' => ProductionOrder::class,
                    'reference_id' => $order->id,
                    'notes' => "Material issued for production order {$order->order_number}",
                    'created_by' => auth()->id(),
                ]);

                $item->issue();

                // Create accounting entry for WIP
                if (method_exists($this->accountingService, 'createManufacturingEntry')) {
                    $this->accountingService->createManufacturingEntry(
                        $order,
                        'material_issue',
                        $item->total_cost,
                        "Material issue: {$item->product->name}"
                    );
                }
            }
        });
    }

    /**
     * Record production output.
     */
    public function recordProduction(ProductionOrder $order, float $quantity, float $scrapQuantity = 0.0): void
    {
        DB::transaction(function () use ($order, $quantity, $scrapQuantity) {
            // Update production order
            $order->increment('quantity_produced', $quantity);
            $order->increment('quantity_scrapped', $scrapQuantity);

            // Create stock movement for finished goods
            StockMovement::create([
                'branch_id' => $order->branch_id,
                'product_id' => $order->product_id,
                'warehouse_id' => $order->warehouse_id,
                'direction' => 'in',
                'qty' => $quantity,
                'reference_type' => ProductionOrder::class,
                'reference_id' => $order->id,
                'notes' => "Production output for order {$order->order_number}",
                'created_by' => auth()->id(),
            ]);

            // Update product cost based on actual manufacturing cost
            if ($order->quantity_produced > 0) {
                $unitCost = $order->actual_cost / $order->quantity_produced;
                $order->product->update(['cost' => $unitCost]);
            }

            // Create accounting entry
            if (method_exists($this->accountingService, 'createManufacturingEntry')) {
                $this->accountingService->createManufacturingEntry(
                    $order,
                    'finished_good',
                    $quantity * $order->product->cost,
                    "Production output: {$quantity} units"
                );
            }

            // Auto-complete if all quantity produced
            if ($order->remaining_quantity <= 0) {
                $order->complete();
            }
        });
    }

    /**
     * Complete a production order.
     */
    public function completeProductionOrder(ProductionOrder $order): ProductionOrder
    {
        DB::transaction(function () use ($order) {
            // Calculate actual cost
            $materialCost = $order->items->sum('total_cost');
            $laborCost = $order->operations->sum(function ($op) {
                return ($op->actual_duration_minutes / 60) * ($op->workCenter->cost_per_hour ?? 0);
            });

            $order->update([
                'status' => 'completed',
                'actual_cost' => $materialCost + $laborCost,
                'actual_end_date' => now(),
            ]);
        });

        return $order;
    }

    /**
     * Cancel a production order.
     */
    public function cancelProductionOrder(ProductionOrder $order, string $reason): ProductionOrder
    {
        DB::transaction(function () use ($order, $reason) {
            // Return issued materials if any
            foreach ($order->items->where('is_issued', true) as $item) {
                $unconsumed = $item->remaining_quantity;
                if ($unconsumed > 0) {
                    StockMovement::create([
                        'branch_id' => $order->branch_id,
                        'product_id' => $item->product_id,
                        'warehouse_id' => $order->warehouse_id,
                        'direction' => 'in',
                        'qty' => $unconsumed,
                        'reference_type' => ProductionOrder::class,
                        'reference_id' => $order->id,
                        'notes' => "Material return from cancelled order: {$reason}",
                        'created_by' => auth()->id(),
                    ]);
                }
            }

            $order->update([
                'status' => 'cancelled',
                'notes' => ($order->notes ?? '')."\n[CANCELLED] {$reason}",
            ]);
        });

        return $order;
    }

    /**
     * Get production reports.
     */
    public function getProductionReport(int $branchId, array $filters = []): array
    {
        $query = ProductionOrder::where('branch_id', $branchId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $orders = $query->with(['product', 'bom'])->get();

        return [
            'total_orders' => $orders->count(),
            'total_planned' => $orders->sum('quantity_planned'),
            'total_produced' => $orders->sum('quantity_produced'),
            'total_scrapped' => $orders->sum('quantity_scrapped'),
            'estimated_cost' => $orders->sum('estimated_cost'),
            'actual_cost' => $orders->sum('actual_cost'),
            'orders' => $orders,
        ];
    }
}
