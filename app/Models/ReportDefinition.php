<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class ReportDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_id',
        'report_key',
        'report_name',
        'report_name_ar',
        'description',
        'description_ar',
        'report_type',
        'available_columns',
        'default_columns',
        'available_filters',
        'default_filters',
        'available_groupings',
        'chart_options',
        'data_source',
        'query_template',
        'supports_export',
        'export_formats',
        'supports_scheduling',
        'is_branch_specific',
        'is_system',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'available_columns' => 'array',
        'default_columns' => 'array',
        'available_filters' => 'array',
        'default_filters' => 'array',
        'available_groupings' => 'array',
        'chart_options' => 'array',
        'export_formats' => 'array',
        'supports_export' => 'boolean',
        'supports_scheduling' => 'boolean',
        'is_branch_specific' => 'boolean',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function exportLayouts(): HasMany
    {
        return $this->hasMany(ExportLayout::class);
    }

    public function getLocalizedNameAttribute(): string
    {
        $locale = app()->getLocale();

        return $locale === 'ar' && $this->report_name_ar ? $this->report_name_ar : $this->report_name;
    }

    public function getLocalizedDescriptionAttribute(): ?string
    {
        $locale = app()->getLocale();

        return $locale === 'ar' && $this->description_ar ? $this->description_ar : $this->description;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('is_system', true);
    }

    public function scopeForModule(Builder $query, $moduleId): Builder
    {
        return $query->where('module_id', $moduleId);
    }

    public function scopeBranchSpecific(Builder $query): Builder
    {
        return $query->where('is_branch_specific', true);
    }

    public function scopeGlobal(Builder $query): Builder
    {
        return $query->where('is_branch_specific', false);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('report_name');
    }
}
