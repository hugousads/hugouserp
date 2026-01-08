<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_return_id',
        'sale_item_id',
        'product_id',
        'branch_id',
        'qty_returned',
        'qty_original',
        'unit_price',
        'discount',
        'tax_amount',
        'line_total',
        'condition',
        'reason',
        'notes',
        'restock',
        'restocked_by',
        'restocked_at',
    ];

    protected $casts = [
        'qty_returned' => 'decimal:3',
        'qty_original' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'restock' => 'boolean',
        'restocked_at' => 'datetime',
    ];

    // Condition constants
    public const CONDITION_NEW = 'new';
    public const CONDITION_USED = 'used';
    public const CONDITION_DAMAGED = 'damaged';
    public const CONDITION_DEFECTIVE = 'defective';

    protected static function boot()
    {
        parent::boot();

        // Auto-calculate line total when creating/updating
        static::saving(function ($item) {
            $subtotal = bcmul((string)$item->qty_returned, (string)$item->unit_price, 2);
            $total = bcadd($subtotal, (string)$item->tax_amount, 2);
            $item->line_total = bcsub($total, (string)$item->discount, 2);
        });
    }

    /**
     * Relationships
     */
    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function restockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restocked_by');
    }

    /**
     * Check if item should be restocked to inventory
     */
    public function shouldRestock(): bool
    {
        return $this->restock && in_array($this->condition, [self::CONDITION_NEW, self::CONDITION_USED]);
    }

    /**
     * Mark item as restocked
     */
    public function markAsRestocked(int $userId): bool
    {
        if ($this->restocked_at) {
            return false; // Already restocked
        }

        $this->update([
            'restocked_by' => $userId,
            'restocked_at' => now(),
        ]);

        return true;
    }

    /**
     * Calculate maximum returnable quantity
     */
    public function getMaxReturnableQty(): float
    {
        // Can't return more than originally sold
        $alreadyReturned = static::where('sale_item_id', $this->sale_item_id)
            ->where('sales_return_id', '!=', $this->sales_return_id ?? 0)
            ->sum('qty_returned');

        return max(0, $this->qty_original - $alreadyReturned);
    }
}
