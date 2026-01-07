<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class VehicleModel extends Model
{
    protected $fillable = [
        'brand',
        'model',
        'year_from',
        'year_to',
        'category',
        'engine_type',
        'is_active',
    ];

    protected $casts = [
        'year_from' => 'integer',
        'year_to' => 'integer',
        'is_active' => 'boolean',
    ];

    public function compatibilities(): HasMany
    {
        return $this->hasMany(ProductCompatibility::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_compatibilities')
            ->withPivot(['oem_number', 'position', 'notes', 'is_verified'])
            ->withTimestamps();
    }

    public function getDisplayNameAttribute(): string
    {
        $name = "{$this->brand} {$this->model}";

        if ($this->year_from && $this->year_to) {
            $name .= " ({$this->year_from}-{$this->year_to})";
        } elseif ($this->year_from) {
            $name .= " ({$this->year_from}+)";
        }

        return $name;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForBrand(Builder $query, string $brand): Builder
    {
        return $query->where('brand', $brand);
    }

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where(function ($q) use ($year) {
            $q->whereNull('year_from')
                ->orWhere('year_from', '<=', $year);
        })->where(function ($q) use ($year) {
            $q->whereNull('year_to')
                ->orWhere('year_to', '>=', $year);
        });
    }
}
