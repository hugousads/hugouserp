<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Services\Contracts\PurchaseServiceInterface;
use App\Traits\HandlesServiceErrors;
use App\Traits\HasRequestContext;
use Illuminate\Support\Facades\DB;

class PurchaseService implements PurchaseServiceInterface
{
    use HandlesServiceErrors;
    use HasRequestContext;

    protected function branchIdOrFail(): int
    {
        $branchId = $this->currentBranchId();

        if ($branchId === null) {
            throw new \InvalidArgumentException('Branch context is required for purchase operations.');
        }

        return $branchId;
    }

    protected function findBranchPurchaseOrFail(int $id): Purchase
    {
        $branchId = $this->branchIdOrFail();

        return Purchase::where('branch_id', $branchId)->findOrFail($id);
    }

    public function create(array $payload): Purchase
    {
        return $this->handleServiceOperation(
            callback: fn () => DB::transaction(function () use ($payload) {
                // Controller provides branch_id in payload after validation
                // Service validates it exists as defense-in-depth
                if (!isset($payload['branch_id'])) {
                    $branchId = $this->branchIdOrFail();
                } else {
                    $branchId = (int) $payload['branch_id'];
                }
                
                $p = Purchase::create([
                    'branch_id' => $branchId,
                    'warehouse_id' => $payload['warehouse_id'] ?? null,
                    'supplier_id' => $payload['supplier_id'] ?? null,
                    'status' => 'draft',
                    'sub_total' => 0, 'tax_total' => 0, 'discount_total' => 0, 'grand_total' => 0,
                    'paid_total' => 0, 'due_total' => 0,
                ]);
                
                $subtotal = '0';
                
                foreach ($payload['items'] ?? [] as $it) {
                    // Skip invalid items without required fields
                    if (!isset($it['product_id']) || !isset($it['qty'])) {
                        continue;
                    }
                    
                    $qty = (float) $it['qty'];
                    $unitCost = (float) ($it['price'] ?? 0);
                    
                    // Critical ERP: Validate positive quantities and prices
                    if ($qty <= 0) {
                        throw new \InvalidArgumentException("Quantity must be positive for product {$it['product_id']}");
                    }
                    
                    if ($unitCost < 0) {
                        throw new \InvalidArgumentException("Unit cost cannot be negative for product {$it['product_id']}");
                    }
                    
                    // Use bcmath for precise calculation
                    $lineTotal = bcmul((string) $qty, (string) $unitCost, 2);
                    $subtotal = bcadd($subtotal, $lineTotal, 2);
                    
                    PurchaseItem::create([
                        'purchase_id' => $p->getKey(),
                        'product_id' => $it['product_id'],
                        'qty' => $qty,
                        'unit_cost' => $unitCost,
                        'line_total' => (float) $lineTotal,
                    ]);
                }
                
                $p->sub_total = (float) $subtotal;
                $p->grand_total = $p->sub_total;
                $p->due_total = $p->grand_total;
                
                // Critical ERP: Validate supplier minimum order value
                if ($p->supplier_id) {
                    $supplier = \App\Models\Supplier::find($p->supplier_id);
                    if ($supplier && $supplier->minimum_order_value > 0) {
                        if ($p->grand_total < $supplier->minimum_order_value) {
                            throw new \InvalidArgumentException(
                                "Order total ({$p->grand_total}) is below supplier minimum order value ({$supplier->minimum_order_value})"
                            );
                        }
                    }
                }
                
                $p->save();

                return $p;
            }),
            operation: 'create',
            context: ['payload' => $payload]
        );
    }

    public function approve(int $id): Purchase
    {
        return $this->handleServiceOperation(
            callback: function () use ($id) {
                $p = $this->findBranchPurchaseOrFail($id);
                $p->status = 'approved';
                $p->approved_at = now();
                $p->save();

                return $p;
            },
            operation: 'approve',
            context: ['purchase_id' => $id]
        );
    }

    public function receive(int $id): Purchase
    {
        return $this->handleServiceOperation(
            callback: function () use ($id) {
                $p = $this->findBranchPurchaseOrFail($id);
                $p->status = 'received';
                $p->received_at = now();
                $p->save();
                event(new \App\Events\PurchaseReceived($p));

                return $p;
            },
            operation: 'receive',
            context: ['purchase_id' => $id]
        );
    }

    public function pay(int $id, float $amount): Purchase
    {
        return $this->handleServiceOperation(
            callback: function () use ($id, $amount) {
                $p = $this->findBranchPurchaseOrFail($id);

                // Validate payment amount
                if ($amount <= 0) {
                    throw new \InvalidArgumentException('Payment amount must be positive');
                }

                $remainingDue = max(0, $p->grand_total - $p->paid_total);
                if ($amount > $remainingDue) {
                    throw new \InvalidArgumentException(sprintf(
                        'Payment amount (%.2f) exceeds remaining due (%.2f)',
                        $amount,
                        $remainingDue
                    ));
                }

                // Critical ERP: Use bcmath for precise money calculations
                $newPaidTotal = bcadd((string) $p->paid_total, (string) $amount, 2);
                $p->paid_total = (float) $newPaidTotal;
                
                // Calculate due amount with precision
                $dueAmount = bcsub((string) $p->grand_total, $newPaidTotal, 2);
                $p->due_total = max(0, (float) $dueAmount);
                if ($p->paid_total >= $p->grand_total) {
                    $p->status = 'paid';
                }
                $p->save();

                return $p;
            },
            operation: 'pay',
            context: ['purchase_id' => $id, 'amount' => $amount]
        );
    }

    public function cancel(int $id): Purchase
    {
        return $this->handleServiceOperation(
            callback: function () use ($id) {
                $p = $this->findBranchPurchaseOrFail($id);

                // Prevent cancelling if already received or paid
                if ($p->status === 'received') {
                    throw new \InvalidArgumentException('Cannot cancel a received purchase. Please create a return instead.');
                }
                if ($p->status === 'paid') {
                    throw new \InvalidArgumentException('Cannot cancel a paid purchase. Please refund first.');
                }
                if ($p->status === 'cancelled') {
                    throw new \InvalidArgumentException('Purchase is already cancelled.');
                }

                $p->status = 'cancelled';
                $p->save();

                return $p;
            },
            operation: 'cancel',
            context: ['purchase_id' => $id]
        );
    }
}
