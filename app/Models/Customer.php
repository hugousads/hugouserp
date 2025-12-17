<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Customer extends BaseModel
{
    use LogsActivity;
    protected ?string $moduleKey = 'customers';

    protected $table = 'customers';

    protected $fillable = [
        'uuid', 'code', 'name', 'email', 'phone', 'tax_number',
        'billing_address', 'shipping_address', 'price_group_id',
        'address', 'city', 'country', 'company', 'external_id',
        'status', 'notes', 'loyalty_points', 'customer_tier', 'tier_updated_at',
        'balance', 'credit_limit', 'total_purchases', 'discount_percentage',
        'payment_terms', 'payment_due_days', 'preferred_currency',
        'website', 'fax', 'credit_hold', 'credit_hold_reason',
        'extra_attributes', 'branch_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'extra_attributes' => 'array',
        'loyalty_points' => 'integer',
        'tier_updated_at' => 'datetime',
        'tax_number' => 'encrypted',
        'phone' => 'encrypted',
        'balance' => 'decimal:4',
        'credit_limit' => 'decimal:4',
        'total_purchases' => 'decimal:4',
        'discount_percentage' => 'decimal:4',
        'payment_due_days' => 'integer',
        'credit_hold' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function priceGroup(): BelongsTo
    {
        return $this->belongsTo(PriceGroup::class, 'price_group_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function vehicleContracts(): HasMany
    {
        return $this->hasMany(VehicleContract::class);
    }

    public function rentalContracts(): HasMany
    {
        return $this->hasMany(RentalContract::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    public function scopeOnCreditHold($q)
    {
        return $q->where('credit_hold', true);
    }

    public function scopeWithinCreditLimit($q)
    {
        return $q->whereRaw('balance <= credit_limit');
    }

    // Business logic methods
    public function hasAvailableCredit(float $amount = 0): bool
    {
        if ($this->credit_hold) {
            return false;
        }

        $availableCredit = $this->credit_limit - $this->balance;
        return $availableCredit >= $amount;
    }

    public function getCreditUtilizationAttribute(): float
    {
        if ($this->credit_limit <= 0) {
            return 0;
        }

        return ($this->balance / $this->credit_limit) * 100;
    }

    public function canPurchase(float $amount): bool
    {
        return $this->hasAvailableCredit($amount) && $this->status === 'active';
    }

    public function addBalance(float $amount): void
    {
        $this->increment('balance', $amount);
        $this->increment('total_purchases', $amount);
    }

    public function subtractBalance(float $amount): void
    {
        $this->decrement('balance', $amount);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'phone', 'status', 'loyalty_points', 'customer_tier'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Customer {$this->name} was {$eventName}");
    }
}
