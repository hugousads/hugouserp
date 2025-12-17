<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Sale extends BaseModel
{
    use LogsActivity;
    protected ?string $moduleKey = 'sales';

    protected $table = 'sales';

    protected $with = ['customer', 'createdBy'];

    protected $fillable = [
        'uuid', 'code', 'branch_id', 'warehouse_id', 'customer_id',
        'status', 'channel', 'currency',
        'sub_total', 'discount_total', 'discount_type', 'discount_value',
        'tax_total', 'shipping_total', 'shipping_method', 'tracking_number',
        'grand_total', 'paid_total', 'due_total', 'amount_paid', 'amount_due',
        'payment_status', 'payment_due_date',
        'expected_delivery_date', 'actual_delivery_date',
        'reference_no', 'posted_at', 'sales_person',
        'store_order_id',
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
            // Use configurable invoice prefix from settings
            $prefix = setting('sales.invoice_prefix', 'SO-');
            $m->code = $m->code ?: $prefix.Str::upper(Str::random(8));
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function returnNotes(): HasMany
    {
        return $this->hasMany(ReturnNote::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function scopePosted($q)
    {
        return $q->where('status', 'posted');
    }

    public function scopePaid($q)
    {
        return $q->where('payment_status', 'paid');
    }

    public function scopeUnpaid($q)
    {
        return $q->where('payment_status', 'unpaid');
    }

    public function scopeOverdue($q)
    {
        return $q->where('payment_status', '!=', 'paid')
            ->whereNotNull('payment_due_date')
            ->where('payment_due_date', '<', now());
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

    public function isOverdue(): bool
    {
        return $this->payment_due_date &&
            $this->payment_due_date->isPast() &&
            !$this->isPaid();
    }

    public function isDelivered(): bool
    {
        return $this->actual_delivery_date !== null;
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

    public function storeOrder(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'status', 'grand_total', 'paid_total', 'customer_id', 'branch_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Sale {$this->code} was {$eventName}");
    }
}
