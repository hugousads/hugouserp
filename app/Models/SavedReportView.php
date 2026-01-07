<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class SavedReportView extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'report_type',
        'filters',
        'columns',
        'ordering',
        'description',
        'is_default',
    ];

    protected $casts = [
        'filters' => 'array',
        'columns' => 'array',
        'ordering' => 'array',
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get views for specific report type
     */
    public function scopeForReportType(Builder $query, string $reportType): Builder
    {
        return $query->where('report_type', $reportType);
    }

    /**
     * Scope to get default view for a report type
     */
    public function scopeDefault(Builder $query, string $reportType): Builder
    {
        return $query->where('report_type', $reportType)->where('is_default', true);
    }
}
