<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalUnit extends BaseModel
{
    protected ?string $moduleKey = 'rentals';

    protected $fillable = ['property_id', 'code', 'type', 'status', 'rent', 'deposit', 'extra_attributes'];

    protected $casts = ['rent' => 'decimal:2', 'deposit' => 'decimal:2', 'extra_attributes' => 'array'];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(RentalContract::class, 'unit_id');
    }

    public function scopeForBranch(Builder $query, $branch): Builder
    {
        $id = is_object($branch) ? $branch->getKey() : $branch;

        return $query->whereHas('property', function ($q) use ($id) {
            $q->where('branch_id', $id);
        });
    }
}
