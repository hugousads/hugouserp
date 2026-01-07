<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ModuleCustomField extends Model
{
    protected $table = 'module_custom_fields';

    protected $fillable = [
        'module_id',
        'field_key',
        'field_label',
        'field_label_ar',
        'field_type',
        'field_options',
        'is_required',
        'is_active',
        'sort_order',
        'validation_rules',
        'placeholder',
        'default_value',
    ];

    protected $casts = [
        'field_options' => 'array',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function getLocalizedLabelAttribute(): string
    {
        return app()->getLocale() === 'ar' && $this->field_label_ar ? $this->field_label_ar : $this->field_label;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
