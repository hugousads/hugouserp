<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransaction extends Model
{
    protected $fillable = [
        'bank_account_id',
        'branch_id',
        'reference_number',
        'transaction_date',
        'value_date',
        'type',
        'amount',
        'balance_after',
        'payee_payer',
        'category',
        'description',
        'status',
        'reconciliation_id',
        'journal_entry_id',
        'related_type',
        'related_id',
        'meta',
        'created_by',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'value_date' => 'date',
        'amount' => 'decimal:4',
        'balance_after' => 'decimal:4',
        'meta' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transaction) {
            if (!$transaction->reference_number) {
                $transaction->reference_number = 'BTX-' . date('Ymd') . '-' . uniqid();
            }
        });
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'reconciliation_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * Check if transaction is reconciled
     */
    public function isReconciled(): bool
    {
        return $this->status === 'reconciled' && $this->reconciliation_id !== null;
    }

    /**
     * Check if transaction is a deposit
     */
    public function isDeposit(): bool
    {
        return $this->type === 'deposit';
    }

    /**
     * Check if transaction is a withdrawal
     */
    public function isWithdrawal(): bool
    {
        return $this->type === 'withdrawal';
    }

    /**
     * Get signed amount (positive for deposits, negative for withdrawals)
     */
    public function getSignedAmount(): float
    {
        if ($this->isDeposit() || $this->type === 'interest') {
            return $this->amount;
        }
        
        return -$this->amount;
    }

    /**
     * Scope for unreconciled transactions
     */
    public function scopeUnreconciled($query)
    {
        return $query->whereIn('status', ['pending', 'cleared'])
            ->whereNull('reconciliation_id');
    }

    /**
     * Scope for a date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }
}
