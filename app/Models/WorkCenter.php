<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkCenter extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'code',
        'name',
        'name_ar',
        'description',
        'type',
        'capacity_per_hour',
        'hourly_rate',
        'is_active',
        'operating_hours',
        'location',
        'manager_id',
        'setup_time_hours',
    ];

    protected $casts = [
        'capacity_per_hour' => 'integer',
        'hourly_rate' => 'decimal:4',
        'setup_time_hours' => 'decimal:2',
        'operating_hours' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the branch that owns the work center.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get BOM operations using this work center.
     */
    public function bomOperations(): HasMany
    {
        return $this->hasMany(BomOperation::class);
    }

    /**
     * Get production order operations at this work center.
     */
    public function productionOrderOperations(): HasMany
    {
        return $this->hasMany(ProductionOrderOperation::class);
    }

    /**
     * Scope: Active work centers only.
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Available (not in maintenance).
     */
    public function scopeAvailable(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if work center is available at a given time.
     */
    public function isAvailableAt(\DateTime $dateTime): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if (empty($this->operating_hours)) {
            return true; // 24/7 operation
        }

        $dayOfWeek = strtolower($dateTime->format('l'));
        $time = $dateTime->format('H:i');

        if (! isset($this->operating_hours[$dayOfWeek])) {
            return false;
        }

        $hours = $this->operating_hours[$dayOfWeek];

        return $time >= $hours['start'] && $time <= $hours['end'];
    }
}
