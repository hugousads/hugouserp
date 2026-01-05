<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transfer extends BaseModel
{
    protected ?string $moduleKey = 'inventory';

    /**
     * Fillable fields aligned with migration:
     * 2026_01_04_000003_create_inventory_tables.php
     */
    protected $fillable = [
        'branch_id',
        'reference_number',
        'from_warehouse_id',
        'to_warehouse_id',
        'status',
        'notes',
        'total_value',
        'shipped_at',
        'received_at',
        'created_by',
        'received_by',
        // For BaseModel compatibility
        'extra_attributes',
    ];

    protected $casts = [
        'total_value' => 'decimal:4',
        'shipped_at' => 'datetime',
        'received_at' => 'datetime',
        'extra_attributes' => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(TransferItem::class);
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    public function scopeInTransit($q)
    {
        return $q->where('status', 'in_transit');
    }

    public function scopeCompleted($q)
    {
        return $q->where('status', 'completed');
    }

    // Backward compatibility accessor
    public function getNoteAttribute()
    {
        return $this->notes;
    }
}
