<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ReportTemplate extends Model
{
    use HasFactory;

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'name',
        'description',
        'route_name',
        'default_filters',
        'output_type',
        'export_columns',
        'is_active',
        'module',
        'category',
        'required_permission',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'default_filters' => 'array',
        'export_columns' => 'array',
        'is_active' => 'bool',
    ];
}
