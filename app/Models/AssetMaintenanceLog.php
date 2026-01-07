<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class AssetMaintenanceLog extends Model
{
    protected $fillable = [
        'asset_id',
        'maintenance_date',
        'maintenance_type',
        'description',
        'cost',
        'vendor_id',
        'performed_by',
        'next_maintenance_date',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'maintenance_date' => 'date',
        'cost' => 'decimal:4',
        'next_maintenance_date' => 'date',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'asset_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'vendor_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope for maintenance by type
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('maintenance_type', $type);
    }

    /**
     * Scope for upcoming maintenance
     */
    public function scopeUpcoming(Builder $query, int $days = 30): Builder
    {
        return $query->whereNotNull('next_maintenance_date')
            ->whereBetween('next_maintenance_date', [now(), now()->addDays($days)]);
    }
}
