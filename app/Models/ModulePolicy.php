<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ModulePolicy extends Model
{
    use HasFactory;

    protected $table = 'module_policies';

    protected $fillable = [
        'module_id',
        'branch_id',
        'policy_key',
        'policy_name',
        'policy_description',
        'policy_rules',
        'scope',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'policy_rules' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Scope query to active policies
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope query to specific module
     */
    public function scopeForModule(Builder $query, int $moduleId): Builder
    {
        return $query->where('module_id', $moduleId);
    }

    /**
     * Scope query to specific branch
     */
    public function scopeForBranch(Builder $query, ?int $branchId): Builder
    {
        return $query->where(function ($q) use ($branchId) {
            $q->where('branch_id', $branchId)
                ->orWhereNull('branch_id');
        });
    }

    /**
     * Scope query by scope type
     */
    public function scopeByScope(Builder $query, string $scope): Builder
    {
        return $query->where('scope', $scope);
    }

    /**
     * Scope query ordered by priority
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('priority')->orderBy('policy_name');
    }

    /**
     * Evaluate policy rules against given context
     *
     * @param  bool  $strictComparison  Use strict comparison (===) vs loose comparison (==)
     */
    public function evaluate(array $context, bool $strictComparison = false): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $rules = $this->policy_rules;
        if (empty($rules)) {
            return true;
        }

        // Simple rule evaluation - can be extended with more complex logic
        foreach ($rules as $key => $value) {
            if (! isset($context[$key])) {
                return false;
            }

            $matches = $strictComparison
                ? $context[$key] === $value
                : $context[$key] == $value;

            if (! $matches) {
                return false;
            }
        }

        return true;
    }
}
