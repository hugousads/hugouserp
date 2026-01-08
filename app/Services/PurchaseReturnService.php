<?php

namespace App\Services;

use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\DebitNote;
use App\Models\SupplierPerformanceMetric;
use App\Models\Purchase;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

/**
 * Service class for managing purchase returns and supplier accountability.
 * 
 * Handles the complete workflow of returning items to suppliers including:
 * - Creating and managing purchase returns
 * - Quality control and inspection
 * - Debit note generation
 * - Supplier performance tracking
 * - Inventory adjustments
 */
class PurchaseReturnService
{
    /**
     * Create a new purchase return with validation
     *
     * @param array $data Purchase return data including items
     * @return PurchaseReturn Created purchase return
     * @throws \Exception If validation fails
     */
    public function createReturn(array $data): PurchaseReturn
    {
        return DB::transaction(function () use ($data) {
            // Validate purchase exists
            $purchase = Purchase::findOrFail($data['purchase_id']);
            
            // Create purchase return
            $return = PurchaseReturn::create([
                'purchase_id' => $data['purchase_id'],
                'supplier_id' => $data['supplier_id'] ?? $purchase->supplier_id,
                'branch_id' => $data['branch_id'] ?? $purchase->branch_id,
                'warehouse_id' => $data['warehouse_id'] ?? $purchase->warehouse_id,
                'return_type' => $data['return_type'] ?? PurchaseReturn::TYPE_FULL,
                'reason' => $data['reason'],
                'status' => PurchaseReturn::STATUS_PENDING,
                'created_by' => Auth::id(),
                'notes' => $data['notes'] ?? null,
                'expected_debit_note_amount' => 0,
            ]);
            
            // Add return items
            $totalAmount = 0;
            foreach ($data['items'] as $itemData) {
                $item = PurchaseReturnItem::create([
                    'purchase_return_id' => $return->id,
                    'purchase_item_id' => $itemData['purchase_item_id'],
                    'product_id' => $itemData['product_id'],
                    'qty_returned' => $itemData['qty_returned'],
                    'unit_cost' => $itemData['unit_cost'],
                    'condition' => $itemData['condition'],
                    'batch_number' => $itemData['batch_number'] ?? null,
                    'expiry_date' => $itemData['expiry_date'] ?? null,
                    'notes' => $itemData['notes'] ?? null,
                ]);
                
                $totalAmount += $item->qty_returned * $item->unit_cost;
            }
            
            // Update expected debit note amount
            $return->update([
                'expected_debit_note_amount' => $totalAmount,
            ]);
            
            return $return->fresh('items');
        });
    }
    
    /**
     * Approve a purchase return and create debit note
     *
     * @param int $returnId Purchase return ID
     * @param array $data Additional approval data
     * @return PurchaseReturn Approved purchase return
     */
    public function approveReturn(int $returnId, array $data = []): PurchaseReturn
    {
        return DB::transaction(function () use ($returnId, $data) {
            $return = PurchaseReturn::with(['items', 'supplier'])->findOrFail($returnId);
            
            if (!$return->canBeApproved()) {
                throw new \Exception('Purchase return cannot be approved in current status');
            }
            
            // Update status
            $return->update([
                'status' => PurchaseReturn::STATUS_APPROVED,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);
            
            // Create debit note if amount is greater than zero
            if ($return->expected_debit_note_amount > 0) {
                $debitNote = $this->createDebitNote($return, $data);
                $return->update(['debit_note_id' => $debitNote->id]);
            }
            
            // Update supplier performance metrics
            $this->updateSupplierPerformance($return->supplier_id, 'return');
            
            return $return->fresh(['items', 'debitNote']);
        });
    }
    
    /**
     * Complete a purchase return (items shipped back to supplier)
     *
     * @param int $returnId Purchase return ID
     * @param array $data Shipping data (tracking number, carrier, etc.)
     * @return PurchaseReturn Completed purchase return
     */
    public function completeReturn(int $returnId, array $data = []): PurchaseReturn
    {
        return DB::transaction(function () use ($returnId, $data) {
            $return = PurchaseReturn::findOrFail($returnId);
            
            if (!$return->canBeCompleted()) {
                throw new \Exception('Purchase return cannot be completed in current status');
            }
            
            // Update return with shipping details
            $return->update([
                'status' => PurchaseReturn::STATUS_COMPLETED,
                'completed_by' => Auth::id(),
                'completed_at' => now(),
                'tracking_number' => $data['tracking_number'] ?? null,
                'carrier' => $data['carrier'] ?? null,
                'metadata' => array_merge($return->metadata ?? [], [
                    'shipping_details' => $data,
                    'completed_at' => now()->toIso8601String(),
                ]),
            ]);
            
            // Adjust inventory for returned items
            $this->adjustInventoryForReturn($return);
            
            return $return->fresh();
        });
    }
    
    /**
     * Reject a purchase return
     *
     * @param int $returnId Purchase return ID
     * @param string $reason Rejection reason
     * @return PurchaseReturn Rejected purchase return
     */
    public function rejectReturn(int $returnId, string $reason): PurchaseReturn
    {
        return DB::transaction(function () use ($returnId, $reason) {
            $return = PurchaseReturn::findOrFail($returnId);
            
            if (!$return->canBeRejected()) {
                throw new \Exception('Purchase return cannot be rejected in current status');
            }
            
            $return->update([
                'status' => PurchaseReturn::STATUS_REJECTED,
                'rejected_by' => Auth::id(),
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);
            
            return $return->fresh();
        });
    }
    
    /**
     * Cancel a purchase return
     *
     * @param int $returnId Purchase return ID
     * @param string $reason Cancellation reason
     * @return PurchaseReturn Cancelled purchase return
     */
    public function cancelReturn(int $returnId, string $reason): PurchaseReturn
    {
        return DB::transaction(function () use ($returnId, $reason) {
            $return = PurchaseReturn::findOrFail($returnId);
            
            $return->update([
                'status' => PurchaseReturn::STATUS_CANCELLED,
                'cancelled_by' => Auth::id(),
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);
            
            return $return->fresh();
        });
    }
    
    /**
     * Create a debit note for approved return
     *
     * @param PurchaseReturn $return Purchase return
     * @param array $data Additional debit note data
     * @return DebitNote Created debit note
     */
    protected function createDebitNote(PurchaseReturn $return, array $data = []): DebitNote
    {
        return DebitNote::create([
            'purchase_return_id' => $return->id,
            'supplier_id' => $return->supplier_id,
            'branch_id' => $return->branch_id,
            'amount' => $data['amount'] ?? $return->expected_debit_note_amount,
            'tax_amount' => $data['tax_amount'] ?? 0,
            'status' => DebitNote::STATUS_PENDING,
            'notes' => $data['notes'] ?? "Debit note for purchase return {$return->return_number}",
            'created_by' => Auth::id(),
        ]);
    }
    
    /**
     * Adjust inventory for completed return
     *
     * @param PurchaseReturn $return Purchase return
     * @return void
     */
    protected function adjustInventoryForReturn(PurchaseReturn $return): void
    {
        foreach ($return->items as $item) {
            // Deduct from inventory (items returned to supplier)
            // This should integrate with your inventory/stock service
            // Example:
            // app(InventoryService::class)->adjustStock([
            //     'product_id' => $item->product_id,
            //     'warehouse_id' => $return->warehouse_id,
            //     'qty' => -$item->qty_returned,
            //     'type' => 'purchase_return',
            //     'reference_id' => $return->id,
            // ]);
        }
    }
    
    /**
     * Update supplier performance metrics
     *
     * @param int $supplierId Supplier ID
     * @param string $type Metric type (return, delivery, quality)
     * @return void
     */
    protected function updateSupplierPerformance(int $supplierId, string $type): void
    {
        $currentPeriod = Carbon::now()->format('Y-m');
        
        $metric = SupplierPerformanceMetric::firstOrCreate([
            'supplier_id' => $supplierId,
            'period' => $currentPeriod,
        ], [
            'total_orders' => 0,
            'on_time_deliveries' => 0,
            'total_items_ordered' => 0,
            'total_items_returned' => 0,
            'defect_rate' => 0,
        ]);
        
        if ($type === 'return') {
            $metric->increment('total_returns');
            
            // Calculate return rate
            $totalReturns = PurchaseReturn::where('supplier_id', $supplierId)
                ->whereYear('created_at', Carbon::now()->year)
                ->whereMonth('created_at', Carbon::now()->month)
                ->sum(DB::raw('(SELECT SUM(qty_returned) FROM purchase_return_items WHERE purchase_return_id = purchase_returns.id)'));
            
            $totalOrders = Purchase::where('supplier_id', $supplierId)
                ->whereYear('created_at', Carbon::now()->year)
                ->whereMonth('created_at', Carbon::now()->month)
                ->sum(DB::raw('(SELECT SUM(quantity) FROM purchase_items WHERE purchase_id = purchases.id)'));
            
            if ($totalOrders > 0) {
                $metric->update([
                    'total_items_returned' => $totalReturns,
                    'total_items_ordered' => $totalOrders,
                    'return_rate' => ($totalReturns / $totalOrders) * 100,
                ]);
            }
        }
    }
    
    /**
     * Get return statistics for a supplier
     *
     * @param int $supplierId Supplier ID
     * @param array $filters Date filters
     * @return array Statistics
     */
    public function getSupplierReturnStatistics(int $supplierId, array $filters = []): array
    {
        $query = PurchaseReturn::where('supplier_id', $supplierId);
        
        if (isset($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }
        
        if (isset($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }
        
        $totalReturns = $query->count();
        $totalAmount = $query->sum('expected_debit_note_amount');
        $approvedReturns = $query->where('status', PurchaseReturn::STATUS_APPROVED)->count();
        
        return [
            'total_returns' => $totalReturns,
            'total_amount' => $totalAmount,
            'approved_returns' => $approvedReturns,
            'approval_rate' => $totalReturns > 0 ? ($approvedReturns / $totalReturns) * 100 : 0,
        ];
    }
    
    /**
     * Get return statistics by condition
     *
     * @param array $filters Optional filters
     * @return array Statistics grouped by condition
     */
    public function getReturnStatisticsByCondition(array $filters = []): array
    {
        $query = PurchaseReturnItem::query();
        
        if (isset($filters['from_date'])) {
            $query->whereHas('purchaseReturn', function ($q) use ($filters) {
                $q->where('created_at', '>=', $filters['from_date']);
            });
        }
        
        if (isset($filters['to_date'])) {
            $query->whereHas('purchaseReturn', function ($q) use ($filters) {
                $q->where('created_at', '<=', $filters['to_date']);
            });
        }
        
        return $query->select('condition', DB::raw('COUNT(*) as count'), DB::raw('SUM(qty_returned) as total_qty'))
            ->groupBy('condition')
            ->get()
            ->toArray();
    }
}
