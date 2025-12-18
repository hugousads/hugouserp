<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RentalContract extends BaseModel
{
    use SoftDeletes;
    protected ?string $moduleKey = 'rentals';

    protected $fillable = [
        'branch_id', 'unit_id', 'tenant_id', 'rental_period_id', 'custom_days', 
        'start_date', 'end_date', 'rent', 'deposit', 'status', 
        'renewal_notice_days', 'auto_renew', 'renewal_term_months', 
        'deposit_refunded', 'deposit_refund_date',
        'extra_attributes'
    ];

    protected $casts = [
        'start_date' => 'date', 
        'end_date' => 'date', 
        'rent' => 'decimal:2', 
        'deposit' => 'decimal:2',
        'deposit_refunded' => 'decimal:2',
        'deposit_refund_date' => 'date',
        'custom_days' => 'integer',
        'renewal_notice_days' => 'integer',
        'renewal_term_months' => 'integer',
        'auto_renew' => 'boolean'
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(RentalUnit::class, 'unit_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function rentalPeriod(): BelongsTo
    {
        return $this->belongsTo(RentalPeriod::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(RentalInvoice::class, 'contract_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RentalPayment::class, 'contract_id');
    }

    public function calculateEndDate(): ?string
    {
        if (! $this->start_date || ! $this->rentalPeriod) {
            return null;
        }

        $period = $this->rentalPeriod;
        $startDate = $this->start_date;

        if ($period->period_type === 'custom' && $this->custom_days) {
            return $startDate->addDays($this->custom_days)->format('Y-m-d');
        }

        return match ($period->duration_unit) {
            'days' => $startDate->addDays($period->duration_value)->format('Y-m-d'),
            'weeks' => $startDate->addWeeks($period->duration_value)->format('Y-m-d'),
            'months' => $startDate->addMonths($period->duration_value)->format('Y-m-d'),
            'years' => $startDate->addYears($period->duration_value)->format('Y-m-d'),
            default => $startDate->addDays($period->duration_value)->format('Y-m-d'),
        };
    }
}
