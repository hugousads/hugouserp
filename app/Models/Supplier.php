<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Supplier extends BaseModel
{
    use LogsActivity;

    protected ?string $moduleKey = 'suppliers';

    protected $fillable = [
        'branch_id', 'name', 'email', 'phone', 'address', 'tax_number', 'is_active',
        'balance', 'total_purchases', 'average_lead_time_days',
        'payment_terms', 'payment_due_days', 'preferred_currency',
        'quality_rating', 'delivery_rating', 'service_rating', 'total_orders',
        'website', 'fax', 'contact_person', 'contact_person_phone', 'contact_person_email',
        'is_approved', 'notes', 'extra_attributes',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'is_approved' => 'bool',
        'extra_attributes' => 'array',
        'balance' => 'decimal:4',
        'total_purchases' => 'decimal:4',
        'average_lead_time_days' => 'decimal:2',
        'payment_due_days' => 'integer',
        'quality_rating' => 'decimal:2',
        'delivery_rating' => 'decimal:2',
        'service_rating' => 'decimal:2',
        'total_orders' => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(SupplierQuotation::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeApproved($q)
    {
        return $q->where('is_approved', true);
    }

    public function scopeActiveAndApproved($q)
    {
        return $q->where('is_active', true)->where('is_approved', true);
    }

    // Business logic methods
    public function getOverallRatingAttribute(): float
    {
        $ratings = [$this->quality_rating, $this->delivery_rating, $this->service_rating];
        $ratings = array_filter($ratings, fn($r) => $r > 0);

        if (empty($ratings)) {
            return 0;
        }

        return array_sum($ratings) / count($ratings);
    }

    public function updateRating(string $type, float $rating): void
    {
        if (!in_array($type, ['quality', 'delivery', 'service'])) {
            return;
        }

        $field = "{$type}_rating";
        $currentRating = $this->{$field};
        $totalOrders = $this->total_orders;

        if ($totalOrders > 0) {
            // Calculate weighted average
            $newRating = (($currentRating * $totalOrders) + $rating) / ($totalOrders + 1);
            $this->{$field} = round($newRating, 2);
        } else {
            $this->{$field} = $rating;
        }

        $this->save();
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

    public function canReceiveOrders(): bool
    {
        return $this->is_active && $this->is_approved;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'phone', 'is_active', 'is_approved', 'quality_rating', 'delivery_rating', 'service_rating'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Supplier {$this->name} was {$eventName}");
    }
}
