<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNoteApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'credit_note_id',
        'sale_id',
        'applied_amount',
        'application_date',
        'notes',
        'applied_by',
    ];

    protected $casts = [
        'applied_amount' => 'decimal:2',
        'application_date' => 'date',
    ];

    /**
     * Relationships
     */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }
}
