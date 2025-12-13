<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GRNItem extends BaseModel
{
    protected ?string $moduleKey = 'purchases';

    protected $table = 'grn_items';

    protected $fillable = [
        'grn_id', 'product_id', 'purchase_item_id',
        'qty_ordered', 'qty_received', 'qty_rejected', 'qty_accepted',
        'uom', 'unit_cost', 'quality_status', 'rejection_reason',
        'batch_id', 'serial_numbers', 'notes',
        'extra_attributes', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'qty_ordered' => 'decimal:4',
        'qty_received' => 'decimal:4',
        'qty_rejected' => 'decimal:4',
        'qty_accepted' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'extra_attributes' => 'array',
    ];

    // Relationships
    public function grn(): BelongsTo
    {
        return $this->belongsTo(GoodsReceivedNote::class, 'grn_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class, 'purchase_item_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'batch_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Business Logic
    public function hasDiscrepancy(): bool
    {
        return $this->qty_received != $this->qty_ordered || $this->qty_rejected > 0;
    }

    public function getDiscrepancyPercentage(): float
    {
        if ($this->qty_ordered == 0) {
            return 0;
        }

        $acceptedQty = max(0, $this->qty_received - $this->qty_rejected);

        return (abs($this->qty_ordered - $acceptedQty) / $this->qty_ordered) * 100;
    }

    public function isFullyReceived(): bool
    {
        return $this->qty_received >= $this->qty_ordered && $this->qty_rejected == 0;
    }

    public function isPartiallyReceived(): bool
    {
        return $this->qty_received > 0 && $this->qty_received < $this->qty_ordered;
    }
}
