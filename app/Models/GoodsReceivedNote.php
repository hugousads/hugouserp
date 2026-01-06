<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoodsReceivedNote extends BaseModel
{
    use SoftDeletes;

    protected ?string $moduleKey = 'purchases';

    protected $table = 'goods_received_notes';

    /**
     * Fillable fields aligned with migration:
     * 2026_01_04_000005_create_sales_purchases_tables.php
     */
    protected $fillable = [
        'branch_id',
        'warehouse_id',
        'purchase_id',
        'supplier_id',
        'reference_number',
        'supplier_delivery_note',
        'status',
        'received_date',
        'notes',
        'received_by_name',
        'received_by',
        'inspected_by',
        'inspected_at',
    ];

    protected $casts = [
        'received_date' => 'date',
        'inspected_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function ($model) {
            if (!$model->reference_number) {
                $model->reference_number = 'GRN-' . date('Ymd') . '-' . str_pad((string) (static::whereDate('created_at', today())->count() + 1), 5, '0', STR_PAD_LEFT);
            }
        });
    }

    // Relationships
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function inspectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GRNItem::class, 'grn_id');
    }

    // Backward compatibility accessor
    public function getCodeAttribute()
    {
        return $this->reference_number;
    }

    public function getDeliveryNoteNoAttribute()
    {
        return $this->supplier_delivery_note;
    }

    // Scopes
    public function scopePendingInspection($query)
    {
        return $query->where('status', 'inspecting');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'completed');
    }

    // Business Logic
    public function approve(int $approvedBy): void
    {
        $this->update([
            'status' => 'completed',
            'inspected_by' => $approvedBy,
            'inspected_at' => now(),
        ]);
    }

    public function reject(int $rejectedBy, string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'inspected_by' => $rejectedBy,
            'inspected_at' => now(),
            'notes' => $reason,
        ]);
    }

    public function canBeApproved(): bool
    {
        return in_array($this->status, ['pending', 'inspecting']);
    }

    public function getTotalQuantityReceived(): float
    {
        return (float) $this->items->sum('received_quantity');
    }

    public function getTotalQuantityAccepted(): float
    {
        return (float) $this->items->sum('accepted_quantity');
    }

    public function getTotalQuantityRejected(): float
    {
        return (float) $this->items->sum('rejected_quantity');
    }

    public function hasDiscrepancies(): bool
    {
        return $this->items->contains(function ($item) {
            return $item->received_quantity != $item->expected_quantity || $item->rejected_quantity > 0;
        });
    }
}
