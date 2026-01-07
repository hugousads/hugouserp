<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends BaseModel
{

    protected $table = 'expenses';

    /**
     * Fillable fields aligned with migration:
     * 2026_01_04_000007_create_accounting_tables.php
     */
    protected $fillable = [
        'branch_id',
        'category_id',
        'reference_number',
        'expense_date',
        'amount',
        'tax_id',
        'tax_amount',
        'total_amount',
        'payment_method',
        'bank_account_id',
        'status',
        'description',
        'vendor_name',
        'receipt_number',
        'attachments',
        'journal_entry_id',
        'created_by',
        'approved_by',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'total_amount' => 'decimal:4',
        'attachments' => 'array',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Backward compatibility accessor
    public function getAttachmentAttribute()
    {
        return $this->attachments[0] ?? null;
    }

    public function getIsRecurringAttribute(): bool
    {
        return false; // Not supported in migration
    }
}
