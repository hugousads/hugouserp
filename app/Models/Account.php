<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $table = 'accounts';

    protected $fillable = [
        'branch_id',
        'account_number',
        'name',
        'name_ar',
        'type',
        'currency_code',
        'requires_currency',
        'account_category',
        'sub_category',
        'parent_id',
        'is_active',
        'description',
        'metadata',
    ];

    /**
     * Sensitive columns that should not be mass-assignable.
     * 'balance' should only be updated through journal entries.
     * 'is_system_account' should only be set by system/seeder.
     */
    protected $guarded = ['balance', 'is_system_account'];

    protected $casts = [
        'balance' => 'decimal:2',
        'is_active' => 'boolean',
        'requires_currency' => 'boolean',
        'is_system_account' => 'boolean',
        'metadata' => 'array',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(AccountMapping::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function getLocalizedNameAttribute(): string
    {
        return app()->getLocale() === 'ar' && $this->name_ar ? $this->name_ar : $this->name;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('account_category', $category);
    }

    public function isAsset(): bool
    {
        return $this->type === 'asset';
    }

    public function isLiability(): bool
    {
        return $this->type === 'liability';
    }

    public function isEquity(): bool
    {
        return $this->type === 'equity';
    }

    public function isRevenue(): bool
    {
        return $this->type === 'revenue';
    }

    public function isExpense(): bool
    {
        return $this->type === 'expense';
    }
}
