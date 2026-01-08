<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\ReturnRefund;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\StockMovement;
use App\Services\Traits\HandlesServiceOperations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalesReturnService
{
    use HandlesServiceOperations;

    public function __construct(
        protected StockService $stockService,
        protected AccountingService $accountingService
    ) {}

    /**
     * Create a new sales return
     *
     * @param array $data {
     *     @type int $sale_id Original sale ID
     *     @type int $branch_id Branch ID
     *     @type int|null $warehouse_id Warehouse for restocking
     *     @type string $reason Return reason
     *     @type array $items Array of items to return
     *     @type string|null $notes Customer notes
     * }
     * @return SalesReturn
     */
    public function createReturn(array $data): SalesReturn
    {
        return $this->handleServiceOperation(
            callback: fn() => DB::transaction(function () use ($data) {
                // Validate sale exists
                $sale = Sale::with(['items.product', 'customer'])->findOrFail($data['sale_id']);
                
                // Validate branch access
                abort_if(
                    !empty($data['branch_id']) && $sale->branch_id !== $data['branch_id'],
                    422,
                    'Branch mismatch between sale and return'
                );

                // Create the return record
                $return = SalesReturn::create([
                    'sale_id' => $sale->id,
                    'branch_id' => $sale->branch_id,
                    'warehouse_id' => $data['warehouse_id'] ?? $sale->warehouse_id,
                    'customer_id' => $sale->customer_id,
                    'return_type' => $this->determineReturnType($data['items'], $sale->items),
                    'status' => SalesReturn::STATUS_PENDING,
                    'reason' => $data['reason'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'currency' => $sale->currency,
                    'refund_method' => $data['refund_method'] ?? 'original',
                    'created_by' => auth()->id(),
                ]);

                // Add return items
                foreach ($data['items'] as $itemData) {
                    $saleItem = $sale->items()->findOrFail($itemData['sale_item_id']);
                    
                    // Validate return quantity
                    $maxQty = $this->getMaxReturnableQty($saleItem);
                    $qtyToReturn = (float)($itemData['qty'] ?? 0);
                    
                    abort_if(
                        $qtyToReturn > $maxQty,
                        422,
                        "Cannot return {$qtyToReturn} units of {$saleItem->product->name}. Maximum returnable: {$maxQty}"
                    );

                    $returnItem = SalesReturnItem::create([
                        'sales_return_id' => $return->id,
                        'sale_item_id' => $saleItem->id,
                        'product_id' => $saleItem->product_id,
                        'branch_id' => $return->branch_id,
                        'qty_returned' => $qtyToReturn,
                        'qty_original' => $saleItem->qty,
                        'unit_price' => $saleItem->unit_price,
                        'discount' => $this->calculateItemDiscount($saleItem, $qtyToReturn),
                        'tax_amount' => $this->calculateItemTax($saleItem, $qtyToReturn),
                        'condition' => $itemData['condition'] ?? SalesReturnItem::CONDITION_NEW,
                        'reason' => $itemData['reason'] ?? null,
                        'notes' => $itemData['notes'] ?? null,
                        'restock' => $itemData['restock'] ?? true,
                    ]);
                }

                // Calculate totals
                $return->calculateTotals();

                // Log activity
                Log::info('Sales return created', [
                    'return_id' => $return->id,
                    'return_number' => $return->return_number,
                    'sale_id' => $sale->id,
                    'total_amount' => $return->total_amount,
                ]);

                return $return->load(['items.product', 'sale', 'customer']);
            }),
            operation: 'create_return',
            context: $data
        );
    }

    /**
     * Approve a sales return
     */
    public function approveReturn(int $returnId, ?int $userId = null): SalesReturn
    {
        return $this->handleServiceOperation(
            callback: fn() => DB::transaction(function () use ($returnId, $userId) {
                $return = SalesReturn::with(['items.product', 'customer'])->findOrFail($returnId);
                $userId = $userId ?? auth()->id();

                abort_if(
                    !$return->canBeApproved(),
                    422,
                    "Return {$return->return_number} cannot be approved in {$return->status} status"
                );

                // Approve the return
                $return->approve($userId);

                // Create credit note if applicable
                if ($return->refund_method !== 'cash' || $return->refund_amount > 0) {
                    $creditNote = $this->createCreditNote($return, $userId);
                    
                    // Auto-apply to customer balance if configured
                    if ($creditNote->auto_apply && $return->customer_id) {
                        $this->applyToCustomerBalance($creditNote, $return->customer_id);
                    }
                }

                // Restock items if needed
                $this->restockItems($return, $userId);

                Log::info('Sales return approved', [
                    'return_id' => $return->id,
                    'return_number' => $return->return_number,
                    'approved_by' => $userId,
                ]);

                return $return->refresh()->load(['items.product', 'creditNotes', 'customer']);
            }),
            operation: 'approve_return',
            context: ['return_id' => $returnId]
        );
    }

    /**
     * Process refund for an approved return
     */
    public function processRefund(int $returnId, array $refundData): ReturnRefund
    {
        return $this->handleServiceOperation(
            callback: fn() => DB::transaction(function () use ($returnId, $refundData) {
                $return = SalesReturn::with(['creditNotes', 'customer'])->findOrFail($returnId);
                $userId = auth()->id();

                abort_if(
                    !$return->canBeProcessed(),
                    422,
                    "Return {$return->return_number} cannot be processed in {$return->status} status"
                );

                // Create refund record
                $refund = ReturnRefund::create([
                    'sales_return_id' => $return->id,
                    'credit_note_id' => $return->creditNotes->first()?->id,
                    'branch_id' => $return->branch_id,
                    'refund_method' => $refundData['method'] ?? $return->refund_method,
                    'amount' => $refundData['amount'] ?? $return->refund_amount,
                    'currency' => $return->currency,
                    'reference_number' => $refundData['reference_number'] ?? null,
                    'transaction_id' => $refundData['transaction_id'] ?? null,
                    'status' => ReturnRefund::STATUS_PENDING,
                    'notes' => $refundData['notes'] ?? null,
                    'bank_name' => $refundData['bank_name'] ?? null,
                    'account_number' => $refundData['account_number'] ?? null,
                    'card_last_four' => $refundData['card_last_four'] ?? null,
                    'created_by' => $userId,
                ]);

                // Process the refund based on method
                $this->executeRefund($refund, $refundData);

                // Complete the refund
                $refund->complete($userId, $refundData['transaction_id'] ?? null);

                // Mark return as completed
                $return->complete($userId);

                // Create accounting entries
                $this->createRefundAccountingEntry($return, $refund);

                Log::info('Return refund processed', [
                    'return_id' => $return->id,
                    'refund_id' => $refund->id,
                    'amount' => $refund->amount,
                    'method' => $refund->refund_method,
                ]);

                return $refund->load(['salesReturn', 'creditNote']);
            }),
            operation: 'process_refund',
            context: ['return_id' => $returnId, 'refund_data' => $refundData]
        );
    }

    /**
     * Reject a sales return
     */
    public function rejectReturn(int $returnId, ?string $reason = null, ?int $userId = null): SalesReturn
    {
        return $this->handleServiceOperation(
            callback: fn() => DB::transaction(function () use ($returnId, $reason, $userId) {
                $return = SalesReturn::findOrFail($returnId);
                $userId = $userId ?? auth()->id();

                abort_if(
                    $return->status !== SalesReturn::STATUS_PENDING,
                    422,
                    "Return {$return->return_number} cannot be rejected in {$return->status} status"
                );

                $return->reject($userId, $reason);

                Log::info('Sales return rejected', [
                    'return_id' => $return->id,
                    'return_number' => $return->return_number,
                    'reason' => $reason,
                    'rejected_by' => $userId,
                ]);

                return $return->refresh();
            }),
            operation: 'reject_return',
            context: ['return_id' => $returnId, 'reason' => $reason]
        );
    }

    /**
     * Create credit note from sales return
     */
    protected function createCreditNote(SalesReturn $return, int $userId): CreditNote
    {
        $creditNote = CreditNote::create([
            'sales_return_id' => $return->id,
            'sale_id' => $return->sale_id,
            'branch_id' => $return->branch_id,
            'customer_id' => $return->customer_id,
            'type' => CreditNote::TYPE_RETURN,
            'status' => CreditNote::STATUS_APPROVED,
            'amount' => $return->refund_amount,
            'currency' => $return->currency,
            'reason' => "Credit note for return {$return->return_number}",
            'notes' => $return->notes,
            'issue_date' => now()->toDateString(),
            'auto_apply' => true,
            'created_by' => $userId,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        return $creditNote;
    }

    /**
     * Restock returned items to inventory
     */
    protected function restockItems(SalesReturn $return, int $userId): void
    {
        foreach ($return->items as $item) {
            if (!$item->shouldRestock()) {
                continue;
            }

            // Add stock back to inventory
            $this->stockService->adjustStock(
                productId: $item->product_id,
                warehouseId: $return->warehouse_id,
                quantity: $item->qty_returned,
                type: StockMovement::TYPE_RETURN,
                reference: "Return: {$return->return_number}",
                notes: "Restocked from sales return - Condition: {$item->condition}"
            );

            // Mark item as restocked
            $item->markAsRestocked($userId);
        }
    }

    /**
     * Execute the refund based on method
     */
    protected function executeRefund(ReturnRefund $refund, array $refundData): void
    {
        $refund->markAsProcessing();

        switch ($refund->refund_method) {
            case ReturnRefund::METHOD_CASH:
                // Cash refund - no external processing needed
                break;

            case ReturnRefund::METHOD_STORE_CREDIT:
                // Apply as store credit to customer
                if ($refund->salesReturn->customer_id) {
                    $this->applyStoreCredit($refund);
                }
                break;

            case ReturnRefund::METHOD_CREDIT_CARD:
            case ReturnRefund::METHOD_BANK_TRANSFER:
                // Would integrate with payment gateway here
                // For now, just mark as processing
                break;

            case ReturnRefund::METHOD_ORIGINAL:
                // Refund to original payment method
                // Would require payment method lookup from original sale
                break;
        }
    }

    /**
     * Apply credit note to customer balance
     */
    protected function applyToCustomerBalance(CreditNote $creditNote, int $customerId): void
    {
        // This would integrate with customer credit/balance system
        // For now, just mark the credit note as applied
        $creditNote->update([
            'status' => CreditNote::STATUS_APPLIED,
            'applied_date' => now()->toDateString(),
        ]);
    }

    /**
     * Apply refund as store credit
     */
    protected function applyStoreCredit(ReturnRefund $refund): void
    {
        // This would add credit to customer's store credit balance
        // Implementation depends on your customer credit system
    }

    /**
     * Create accounting entries for the refund
     */
    protected function createRefundAccountingEntry(SalesReturn $return, ReturnRefund $refund): void
    {
        try {
            // Create journal entry for the refund
            // This would integrate with your accounting system
            // Typical entries:
            // DR: Sales Returns and Allowances (increase)
            // CR: Cash/Bank/Accounts Receivable (decrease)
            
            $this->accountingService->createJournalEntry([
                'branch_id' => $return->branch_id,
                'date' => now()->toDateString(),
                'reference' => $return->return_number,
                'description' => "Sales return refund - {$return->return_number}",
                'type' => 'sales_return',
                'lines' => [
                    [
                        'account_id' => null, // Would be sales returns account
                        'debit' => $refund->amount,
                        'credit' => 0,
                        'description' => 'Sales return',
                    ],
                    [
                        'account_id' => null, // Would be cash/bank account
                        'debit' => 0,
                        'credit' => $refund->amount,
                        'description' => 'Refund payment',
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create refund accounting entry', [
                'return_id' => $return->id,
                'refund_id' => $refund->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Helper methods
     */
    protected function determineReturnType(array $returnItems, $saleItems): string
    {
        $returnItemCount = count($returnItems);
        $saleItemCount = $saleItems->count();

        return $returnItemCount === $saleItemCount ? SalesReturn::TYPE_FULL : SalesReturn::TYPE_PARTIAL;
    }

    protected function getMaxReturnableQty($saleItem): float
    {
        $alreadyReturned = SalesReturnItem::where('sale_item_id', $saleItem->id)
            ->sum('qty_returned');

        return max(0, $saleItem->qty - $alreadyReturned);
    }

    protected function calculateItemDiscount($saleItem, float $qtyReturned): float
    {
        if ($saleItem->qty <= 0) {
            return 0;
        }

        $discountPerUnit = $saleItem->discount / $saleItem->qty;
        return $discountPerUnit * $qtyReturned;
    }

    protected function calculateItemTax($saleItem, float $qtyReturned): float
    {
        if ($saleItem->qty <= 0 || !isset($saleItem->line_total)) {
            return 0;
        }

        // Calculate proportional tax
        $taxPerUnit = ($saleItem->line_total - ($saleItem->unit_price * $saleItem->qty - $saleItem->discount)) / $saleItem->qty;
        return $taxPerUnit * $qtyReturned;
    }

    /**
     * Get return statistics for a branch
     */
    public function getReturnStatistics(int $branchId, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = SalesReturn::where('branch_id', $branchId);

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        return [
            'total_returns' => $query->count(),
            'pending_returns' => (clone $query)->where('status', SalesReturn::STATUS_PENDING)->count(),
            'approved_returns' => (clone $query)->where('status', SalesReturn::STATUS_APPROVED)->count(),
            'completed_returns' => (clone $query)->where('status', SalesReturn::STATUS_COMPLETED)->count(),
            'rejected_returns' => (clone $query)->where('status', SalesReturn::STATUS_REJECTED)->count(),
            'total_refund_amount' => $query->sum('refund_amount'),
            'average_refund_amount' => $query->avg('refund_amount'),
        ];
    }
}
