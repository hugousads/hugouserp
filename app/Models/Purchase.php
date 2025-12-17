<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Purchase extends BaseModel
{
    use LogsActivity;
    protected ?string $moduleKey = 'purchases';

    protected $table = 'purchases';

    protected $with = ['supplier', 'createdBy'];

    protected $fillable = [
        'uuid', 'code', 'branch_id', 'warehouse_id', 'supplier_id',
        'status', 'currency', 'sub_total', 'discount_total', 'discount_type', 'discount_value',
        'tax_total', 'shipping_total', 'grand_total',
        'paid_total', 'due_total', 'amount_paid', 'amount_due',
        'payment_status', 'payment_due_date',
        'expected_delivery_date', 'actual_delivery_date', 'delivery_status',
        'approved_by', 'approved_at', 'requisition_number',
        'reference_no', 'posted_at',
        'notes', 'internal_notes', 'extra_attributes', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'sub_total' => 'decimal:4',
        'discount_total' => 'decimal:4',
        'discount_value' => 'decimal:4',
        'tax_total' => 'decimal:4',
        'shipping_total' => 'decimal:4',
        'grand_total' => 'decimal:4',
        'paid_total' => 'decimal:4',
        'due_total' => 'decimal:4',
        'amount_paid' => 'decimal:4',
        'amount_due' => 'decimal:4',
        'posted_at' => 'datetime',
        'approved_at' => 'datetime',
        'payment_due_date' => 'date',
        'expected_delivery_date' => 'date',
        'actual_delivery_date' => 'date',
        'extra_attributes' => 'array',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function ($m) {
            $m->uuid = $m->uuid ?: (string) Str::uuid();
            // Use configurable purchase order prefix from settings
            $prefix = setting('purchases.purchase_order_prefix', 'PO-');
            $m->code = $m->code ?: $prefix.Str::upper(Str::random(8));
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    public function returnNotes(): HasMany
    {
        return $this->hasMany(ReturnNote::class);
    }

    public function requisitions(): HasMany
    {
        return $this->hasMany(PurchaseRequisition::class, 'converted_to_po_id');
    }

    public function grns(): HasMany
    {
        return $this->hasMany(GoodsReceivedNote::class, 'purchase_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes
    public function scopeApproved($q)
    {
        return $q->whereNotNull('approved_at');
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    public function scopeOverdue($q)
    {
        return $q->where('payment_status', '!=', 'paid')
            ->whereNotNull('payment_due_date')
            ->where('payment_due_date', '<', now());
    }

    // Business Logic
    public function getTotalQuantityReceived(): float
    {
        return $this->grns()->where('status', 'approved')->get()->sum(function ($grn) {
            return $grn->getTotalQuantityAccepted();
        });
    }

    public function isFullyReceived(): bool
    {
        $orderedQty = $this->items->sum('qty');
        $receivedQty = $this->getTotalQuantityReceived();

        return $receivedQty >= $orderedQty;
    }

    public function isPartiallyReceived(): bool
    {
        $receivedQty = $this->getTotalQuantityReceived();

        return $receivedQty > 0 && !$this->isFullyReceived();
    }

    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function getRemainingAmountAttribute(): float
    {
        return max(0, (float) $this->grand_total - $this->total_paid);
    }

    public function isPaid(): bool
    {
        return $this->remaining_amount <= 0 || $this->payment_status === 'paid';
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    public function isOverdue(): bool
    {
        return $this->payment_due_date &&
            $this->payment_due_date->isPast() &&
            !$this->isPaid();
    }

    public function isDelivered(): bool
    {
        return $this->delivery_status === 'completed';
    }

    public function approve(int $userId): void
    {
        $this->approved_by = $userId;
        $this->approved_at = now();
        $this->status = 'approved';
        $this->save();
    }

    public function updatePaymentStatus(): void
    {
        $totalPaid = $this->total_paid;
        $grandTotal = (float) $this->grand_total;

        if ($totalPaid >= $grandTotal) {
            $this->payment_status = 'paid';
        } elseif ($totalPaid > 0) {
            $this->payment_status = 'partial';
        } else {
            $this->payment_status = 'unpaid';
        }

        // Update amount fields for consistency
        $this->amount_paid = $totalPaid;
        $this->amount_due = max(0, $grandTotal - $totalPaid);
        $this->paid_total = $totalPaid;
        $this->due_total = $this->amount_due;

        $this->saveQuietly();
    }

    public function updateDeliveryStatus(): void
    {
        if ($this->isFullyReceived()) {
            $this->delivery_status = 'completed';
        } elseif ($this->isPartiallyReceived()) {
            $this->delivery_status = 'partial';
        } else {
            $this->delivery_status = 'pending';
        }

        $this->saveQuietly();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'status', 'grand_total', 'paid_total', 'supplier_id', 'branch_id', 'approved_by', 'approved_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Purchase {$this->code} was {$eventName}");
    }
}
