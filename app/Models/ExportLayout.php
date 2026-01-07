<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ExportLayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'report_definition_id',
        'layout_name',
        'entity_type',
        'selected_columns',
        'column_order',
        'column_labels',
        'export_format',
        'include_headers',
        'date_format',
        'number_format',
        'is_default',
        'is_shared',
    ];

    protected $casts = [
        'selected_columns' => 'array',
        'column_order' => 'array',
        'column_labels' => 'array',
        'include_headers' => 'boolean',
        'is_default' => 'boolean',
        'is_shared' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reportDefinition(): BelongsTo
    {
        return $this->belongsTo(ReportDefinition::class);
    }

    public function scopeForUser(Builder $query, $userId): Builder
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
                ->orWhere('is_shared', true);
        });
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    public function scopeForEntity(Builder $query, $entityType): Builder
    {
        return $query->where('entity_type', $entityType);
    }

    public function getOrderedColumns(): array
    {
        $columns = $this->selected_columns ?? [];
        $order = $this->column_order ?? [];

        if (empty($order)) {
            return $columns;
        }

        usort($columns, function ($a, $b) use ($order) {
            $posA = array_search($a, $order);
            $posB = array_search($b, $order);

            return ($posA === false ? PHP_INT_MAX : $posA) <=> ($posB === false ? PHP_INT_MAX : $posB);
        });

        return $columns;
    }

    public function getColumnLabel(string $column): string
    {
        return $this->column_labels[$column] ?? $column;
    }
}
