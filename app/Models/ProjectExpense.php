<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectExpense extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'task_id',
        'category',
        'description',
        'amount',
        'currency',
        'currency_id',
        'expense_date',
        'date',
        'vendor',
        'user_id',
        'billable',
        'status',
        'approved_by',
        'approved_date',
        'approved_at',
        'rejection_reason',
        'is_reimbursable',
        'reimbursed_to',
        'reimbursed_at',
        'receipt_path',
        'notes',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'date' => 'date',
        'amount' => 'decimal:2',
        'approved_date' => 'date',
        'approved_at' => 'datetime',
        'reimbursed_at' => 'datetime',
        'is_reimbursable' => 'boolean',
        'metadata' => 'array',
    ];

    // Relationships
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reimbursedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reimbursed_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeNeedsReimbursement($query)
    {
        return $query->where('is_reimbursable', true)
                    ->where('status', 'approved')
                    ->whereNull('reimbursed_at');
    }

    // Business Methods
    public function approve(int $userId): bool
    {
        $this->status = 'approved';
        $this->approved_by = $userId;
        $this->approved_at = now();
        $this->rejection_reason = null;

        return $this->save();
    }

    public function reject(int $userId, string $reason): bool
    {
        $this->status = 'rejected';
        $this->approved_by = $userId;
        $this->approved_at = now();
        $this->rejection_reason = $reason;

        return $this->save();
    }

    public function markAsReimbursed(int $userId): bool
    {
        if (!$this->is_reimbursable || $this->status !== 'approved') {
            return false;
        }

        $this->reimbursed_to = $userId;
        $this->reimbursed_at = now();

        return $this->save();
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function needsReimbursement(): bool
    {
        return $this->is_reimbursable && 
               $this->status === 'approved' && 
               is_null($this->reimbursed_at);
    }

    // Helper method for backwards compatibility
    public function getExpenseDateAttribute($value)
    {
        // If expense_date has a value, return it; otherwise fall back to date
        if ($value !== null) {
            return $value;
        }
        return $this->getRawOriginal('date');
    }
}
