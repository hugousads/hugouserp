<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ReturnNote;
use App\Models\Sale;
use App\Services\Contracts\SaleServiceInterface;
use App\Traits\HandlesServiceErrors;
use App\Traits\HasRequestContext;
use Illuminate\Support\Facades\DB;

class SaleService implements SaleServiceInterface
{
    use HandlesServiceErrors;
    use HasRequestContext;

    protected function branchIdOrFail(): int
    {
        $branchId = $this->currentBranchId();

        if ($branchId === null) {
            throw new \InvalidArgumentException('Branch context is required for sale operations.');
        }

        return $branchId;
    }

    protected function findBranchSaleOrFail(int $id): Sale
    {
        $branchId = $this->branchIdOrFail();

        return Sale::where('branch_id', $branchId)->findOrFail($id);
    }

    public function show(int $id): Sale
    {
        return $this->handleServiceOperation(
            callback: fn () => $this->findBranchSaleOrFail($id)->load('items'),
            operation: 'show',
            context: ['sale_id' => $id]
        );
    }

    /** Return items (full/partial). Negative movement is handled in listeners. */
    public function handleReturn(int $saleId, array $items, ?string $reason = null): ReturnNote
    {
        return $this->handleServiceOperation(
            callback: function () use ($saleId, $items, $reason) {
                $sale = $this->findBranchSaleOrFail($saleId)->load('items');

                return DB::transaction(function () use ($sale, $items, $reason) {
                    // Calculate refund amount first
                    $refund = '0.00';
                    foreach ($items as $it) {
                        // Validate required fields
                        if (!isset($it['product_id']) || !isset($it['qty'])) {
                            continue;
                        }
                        
                        $si = $sale->items->firstWhere('product_id', $it['product_id']);
                        if (! $si) {
                            continue;
                        }
                        // Use quantity column (not qty)
                        $qty = min((float) $it['qty'], (float) $si->quantity);
                        
                        // Skip if qty is zero or negative
                        if ($qty <= 0) {
                            continue;
                        }
                        
                        // Use bcmath for precise money calculation
                        $line = bcmul((string) $qty, (string) $si->unit_price, 2);
                        $refund = bcadd($refund, $line, 2);
                    }

                    // Create return note with correct column name (total_amount)
                    $note = ReturnNote::create([
                        'branch_id' => $sale->branch_id,
                        'sale_id' => $sale->getKey(),
                        'reference_number' => 'RET-' . date('Ymd') . '-' . str_pad((string) (ReturnNote::whereDate('created_at', today())->count() + 1), 5, '0', STR_PAD_LEFT),
                        'type' => 'sale_return',
                        'warehouse_id' => $sale->warehouse_id,
                        'customer_id' => $sale->customer_id,
                        'status' => 'pending',
                        'return_date' => now()->toDateString(),
                        'reason' => $reason,
                        'total_amount' => (float) $refund,
                    ]);

                    $sale->status = 'returned';
                    // Use bcmath to prevent rounding errors in refund calculation
                    // Use correct migration column name
                    $newPaidAmount = bcsub((string) $sale->paid_amount, $refund, 2);
                    $sale->paid_amount = max(0.0, (float) $newPaidAmount);
                    $sale->save();

                    $this->logServiceInfo('handleReturn', 'Sale return processed', [
                        'sale_id' => $sale->getKey(),
                        'return_note_id' => $note->getKey(),
                        'refund_amount' => $refund,
                    ]);

                    return $note;
                });
            },
            operation: 'handleReturn',
            context: ['sale_id' => $saleId, 'items_count' => count($items), 'reason' => $reason]
        );
    }

    public function voidSale(int $saleId, ?string $reason = null): Sale
    {
        return $this->handleServiceOperation(
            callback: function () use ($saleId, $reason) {
                $sale = $this->findBranchSaleOrFail($saleId);
                $sale->status = 'void';
                $sale->notes = trim(($sale->notes ?? '')."\nVOID: ".$reason);
                $sale->save();

                $this->logServiceInfo('voidSale', 'Sale voided', [
                    'sale_id' => $sale->getKey(),
                    'reason' => $reason,
                ]);

                return $sale;
            },
            operation: 'voidSale',
            context: ['sale_id' => $saleId, 'reason' => $reason]
        );
    }

    /** Return array with printable path (PDF/HTML) using PrintingService */
    public function printInvoice(int $saleId): array
    {
        return $this->handleServiceOperation(
            callback: function () use ($saleId) {
                $sale = $this->findBranchSaleOrFail($saleId)->load('items');
                $printer = app(PrintingService::class);

                return $printer->renderPdfOrHtml('prints.sale', ['sale' => $sale], 'sale_'.$sale->id);
            },
            operation: 'printInvoice',
            context: ['sale_id' => $saleId],
            defaultValue: []
        );
    }
}
