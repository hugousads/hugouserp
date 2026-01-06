<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReturnNote extends BaseModel
{
    use SoftDeletes;

    protected ?string $moduleKey = 'sales';

    protected $table = 'return_notes';

    /**
     * Fillable fields aligned with migration:
     * 2026_01_04_000005_create_sales_purchases_tables.php
     */
    protected $fillable = [
        'branch_id',
        'reference_number',
        'type',
        'sale_id',
        'purchase_id',
        'customer_id',
        'supplier_id',
        'warehouse_id',
        'status',
        'return_date',
        'reason',
        'total_amount',
        'refund_method',
        'restock_items',
        'processed_by',
    ];

    protected $casts = [
        'return_date' => 'date',
        'total_amount' => 'decimal:4',
        'restock_items' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    // Backward compatibility accessor
    public function getTotalAttribute()
    {
        return $this->total_amount;
    }

    // Scopes
    public function scopeSaleReturns($query)
    {
        return $query->where('type', 'sale_return');
    }

    public function scopePurchaseReturns($query)
    {
        return $query->where('type', 'purchase_return');
    }
}
