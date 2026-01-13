<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionOrder extends BaseModel
{
    use HasFactory, SoftDeletes;

    /**
     * Fillable fields aligned with migration:
     * 2026_01_04_000009_create_manufacturing_tables.php
     */
    protected $fillable = [
        'branch_id',
        'bom_id',
        'product_id',
        'warehouse_id',
        'order_number',
        'status',
        'priority',
        'quantity_planned',
        'quantity_produced',
        'quantity_scrapped',
        'start_date',
        'due_date',
        'planned_start_date',
        'planned_end_date',
        'actual_start_date',
        'actual_end_date',
        'estimated_cost',
        'actual_cost',
        'material_cost',
        'labor_cost',
        'overhead_cost',
        'sale_id',
        'notes',
        'metadata',
        'created_by',
        'approved_by',
    ];

    protected $casts = [
        'quantity_planned' => 'decimal:4',
        'quantity_produced' => 'decimal:4',
        'quantity_scrapped' => 'decimal:4',
        'estimated_cost' => 'decimal:4',
        'actual_cost' => 'decimal:4',
        'material_cost' => 'decimal:4',
        'labor_cost' => 'decimal:4',
        'overhead_cost' => 'decimal:4',
        'start_date' => 'date',
        'due_date' => 'date',
        'planned_start_date' => 'date',
        'planned_end_date' => 'date',
        'actual_start_date' => 'datetime',
        'actual_end_date' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the branch that owns the production order.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the BOM used.
     */
    public function bom(): BelongsTo
    {
        return $this->belongsTo(BillOfMaterial::class, 'bom_id');
    }

    /**
     * Get the product being manufactured.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the warehouse for finished goods.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get the creator.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the approver.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the linked sale (if make-to-order).
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Get the order items (materials).
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProductionOrderItem::class);
    }

    /**
     * Get the order operations.
     */
    public function operations(): HasMany
    {
        return $this->hasMany(ProductionOrderOperation::class);
    }

    /**
     * Get manufacturing transactions.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(ManufacturingTransaction::class);
    }

    /**
     * Calculate completion percentage.
     */
    public function getCompletionPercentageAttribute(): float
    {
        $plannedQty = (float) ($this->quantity_planned ?? 0);
        // Prevent division by zero
        if ($plannedQty <= 0) {
            return 0.0;
        }

        return ((float) ($this->quantity_produced ?? 0) / $plannedQty) * 100;
    }

    /**
     * Calculate remaining quantity to produce.
     */
    public function getRemainingQuantityAttribute(): float
    {
        return (float) $this->quantity_planned - (float) $this->quantity_produced - (float) $this->quantity_scrapped;
    }

    /**
     * Scope: By status.
     */
    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: In progress.
     */
    public function scopeInProgress(Builder $query): Builder
    {
        return $query->whereIn('status', ['planned', 'in_progress']);
    }

    /**
     * Scope: Completed.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope: By priority.
     */
    public function scopePriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    /**
     * Generate next production order number.
     */
    public static function generateOrderNumber(int $branchId): string
    {
        $prefix = 'PRO';
        $date = now()->format('Ym');

        $lastOrder = static::where('branch_id', $branchId)
            ->where('order_number', 'like', "{$prefix}-{$date}-%")
            ->orderByDesc('id')
            ->first();

        if ($lastOrder) {
            $lastNumber = (int) substr($lastOrder->order_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $date, $newNumber);
    }

    /**
     * Start production.
     */
    public function start(): void
    {
        $this->update([
            'status' => 'in_progress',
            'actual_start_date' => now(),
        ]);
    }

    /**
     * Complete production.
     */
    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'actual_end_date' => now(),
        ]);
    }
}
