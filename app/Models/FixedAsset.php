<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FixedAsset extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'branch_id',
        'asset_code',
        'name',
        'description',
        'category',
        'location',
        'purchase_date',
        'purchase_cost',
        'salvage_value',
        'useful_life_years',
        'useful_life_months',
        'depreciation_method',
        'depreciation_rate',
        'accumulated_depreciation',
        'book_value',
        'depreciation_start_date',
        'last_depreciation_date',
        'status',
        'disposal_date',
        'disposal_amount',
        'supplier_id',
        'serial_number',
        'model',
        'manufacturer',
        'warranty_expiry',
        'assigned_to',
        'notes',
        'meta',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'purchase_cost' => 'decimal:4',
        'salvage_value' => 'decimal:4',
        'accumulated_depreciation' => 'decimal:4',
        'book_value' => 'decimal:4',
        'depreciation_rate' => 'decimal:4',
        'depreciation_start_date' => 'date',
        'last_depreciation_date' => 'date',
        'disposal_date' => 'date',
        'disposal_amount' => 'decimal:4',
        'warranty_expiry' => 'date',
        'meta' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($asset) {
            if (!$asset->asset_code) {
                // Generate unique asset code using timestamp and random component
                // Format: FA-YYYYMMDD-HHMMSS-RAND
                $asset->asset_code = 'FA-' . date('Ymd-His') . '-' . strtoupper(substr(uniqid(), -4));
            }
            
            if (!$asset->book_value) {
                $asset->book_value = $asset->purchase_cost;
            }
            
            if (!$asset->depreciation_start_date) {
                $asset->depreciation_start_date = $asset->purchase_date;
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function depreciations(): HasMany
    {
        return $this->hasMany(AssetDepreciation::class, 'asset_id');
    }

    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(AssetMaintenanceLog::class, 'asset_id');
    }

    /**
     * Check if asset is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if asset is fully depreciated
     */
    public function isFullyDepreciated(): bool
    {
        return $this->book_value <= $this->salvage_value;
    }

    /**
     * Get total useful life in months
     */
    public function getTotalUsefulLifeMonths(): int
    {
        return ($this->useful_life_years * 12) + $this->useful_life_months;
    }

    /**
     * Calculate straight-line depreciation per month
     */
    public function getMonthlyDepreciation(): float
    {
        $depreciableAmount = $this->purchase_cost - $this->salvage_value;
        $totalMonths = $this->getTotalUsefulLifeMonths();
        
        return $totalMonths > 0 ? $depreciableAmount / $totalMonths : 0;
    }

    /**
     * Scope for active assets
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for disposed assets
     */
    public function scopeDisposed($query)
    {
        return $query->whereIn('status', ['disposed', 'sold', 'retired']);
    }

    /**
     * Scope for assets by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
