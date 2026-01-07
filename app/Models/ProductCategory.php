<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * ProductCategory - Product categorization with hierarchy support
 *
 * Extends BaseModel for:
 * - Automatic branch scoping
 * - Common query scopes (active, forBranch, etc.)
 * - Dynamic fields support
 * - Audit logging
 */
class ProductCategory extends BaseModel
{
    protected $table = 'product_categories';

    protected $fillable = [
        'name',
        'name_ar',
        'slug',
        'parent_id',
        'description',
        'image',
        'sort_order',
        'is_active',
        'branch_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function ($model): void {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name).'-'.Str::random(4);
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ProductCategory::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeRoots(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNull('parent_id');
    }

    public function getLocalizedNameAttribute(): string
    {
        $locale = app()->getLocale();

        return $locale === 'ar' && $this->name_ar ? $this->name_ar : $this->name;
    }
}
