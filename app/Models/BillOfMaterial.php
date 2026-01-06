<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillOfMaterial extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'bills_of_materials';

    /**
     * Fillable fields aligned with migration:
     * 2026_01_04_000009_create_manufacturing_tables.php
     */
    protected $fillable = [
        'branch_id',
        'product_id',
        'reference_number',
        'name',
        'version',
        'quantity',
        'yield_percentage',
        'estimated_cost',
        'estimated_time_hours',
        'status',
        'notes',
        'custom_fields',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'yield_percentage' => 'decimal:2',
        'estimated_cost' => 'decimal:4',
        'estimated_time_hours' => 'decimal:2',
        'custom_fields' => 'array',
    ];

    /**
     * Get the branch that owns the BOM.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the finished product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the BOM items (components/materials).
     */
    public function items(): HasMany
    {
        return $this->hasMany(BomItem::class, 'bom_id');
    }

    /**
     * Get the BOM operations.
     */
    public function operations(): HasMany
    {
        return $this->hasMany(BomOperation::class, 'bom_id');
    }

    /**
     * Get production orders using this BOM.
     */
    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class, 'bom_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Backward compatibility accessors
    public function getBomNumberAttribute()
    {
        return $this->reference_number;
    }

    public function getScrapPercentageAttribute()
    {
        return 100 - $this->yield_percentage;
    }

    public function getIsMultiLevelAttribute(): bool
    {
        return $this->items()->whereHas('product', function ($q) {
            $q->whereHas('bom');
        })->exists();
    }

    public function getMetadataAttribute()
    {
        return $this->custom_fields;
    }

    /**
     * Calculate total material cost for this BOM.
     */
    public function calculateMaterialCost(): float
    {
        $cost = 0.0;

        foreach ($this->items as $item) {
            $productCost = $item->product->cost ?? 0.0;
            $itemQuantity = (float) $item->quantity;
            $scrapFactor = 1 + ((float) ($item->scrap_percentage ?? 0) / 100);

            $cost += $productCost * $itemQuantity * $scrapFactor;
        }

        // Apply BOM-level yield percentage
        $yieldFactor = (float) $this->yield_percentage / 100;
        if ($yieldFactor > 0) {
            $cost = $cost / $yieldFactor;
        }

        return $cost;
    }

    /**
     * Calculate total labor cost for this BOM.
     */
    public function calculateLaborCost(): float
    {
        return $this->operations->sum(function ($operation) {
            $durationHours = (float) ($operation->duration_minutes ?? 0) / 60;
            $costPerHour = (float) ($operation->workCenter->cost_per_hour ?? 0);

            return $durationHours * $costPerHour + (float) ($operation->labor_cost ?? 0);
        });
    }

    /**
     * Calculate total production cost.
     */
    public function calculateTotalCost(): float
    {
        return $this->calculateMaterialCost() + $this->calculateLaborCost();
    }

    /**
     * Scope: Active BOMs only.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: Draft BOMs.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Generate next BOM number.
     */
    public static function generateBomNumber(int $branchId): string
    {
        $prefix = 'BOM';
        $date = now()->format('Ym');

        $lastBom = static::where('branch_id', $branchId)
            ->where('reference_number', 'like', "{$prefix}-{$date}-%")
            ->orderByDesc('id')
            ->first();

        if ($lastBom) {
            $lastNumber = (int) substr($lastBom->reference_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $date, $newNumber);
    }
}
