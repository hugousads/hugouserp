<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * BranchScope - Global scope for multi-tenancy branch isolation
 *
 * This scope automatically filters queries by the authenticated user's branch_id,
 * ensuring data isolation between branches. Super Admins can bypass this filter.
 *
 * Usage: Applied automatically via HasBranch trait's bootHasBranch() method
 */
class BranchScope implements Scope
{
    /**
     * Apply the branch scope to a given Eloquent query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Skip scope if running in console (migrations, seeders, etc.)
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return;
        }

        // Skip if the model doesn't have a branch_id column
        if (! $this->hasBranchIdColumn($model)) {
            return;
        }

        // Skip if no authenticated user
        if (! $this->hasAuthenticatedUser()) {
            return;
        }

        $user = $this->getAuthenticatedUser();

        // Skip scope for Super Admins (they can see all branches)
        if ($this->isSuperAdmin($user)) {
            return;
        }

        // Get the user's branch_id
        $branchId = $user->branch_id ?? null;

        // Get additional branches user has access to
        $accessibleBranchIds = $this->getAccessibleBranchIds($user, $branchId);

        // Apply the branch filter
        $table = $model->getTable();

        if (count($accessibleBranchIds) === 1) {
            $builder->where("{$table}.branch_id", $accessibleBranchIds[0]);
        } elseif (count($accessibleBranchIds) > 1) {
            $builder->whereIn("{$table}.branch_id", $accessibleBranchIds);
        } else {
            // User has no branch access - return empty result
            $builder->whereRaw('1 = 0');
        }
    }

    /**
     * Check if the model has a branch_id column.
     */
    protected function hasBranchIdColumn(Model $model): bool
    {
        // Branch model itself doesn't have branch_id - it IS the branch
        if ($model instanceof \App\Models\Branch) {
            return false;
        }

        // Check if the model has branch_id in fillable attributes
        $fillable = $model->getFillable();
        if (in_array('branch_id', $fillable, true)) {
            return true;
        }

        // Check if model has 'branch' in its $with array (eager loaded relation)
        // This is a good indicator that the model has branch_id
        $with = $model->getWith();
        if (in_array('branch', $with, true)) {
            return true;
        }

        return false;
    }

    /**
     * Check if there's an authenticated user.
     */
    protected function hasAuthenticatedUser(): bool
    {
        if (! function_exists('auth')) {
            return false;
        }

        try {
            return auth()->check();
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Get the authenticated user.
     */
    protected function getAuthenticatedUser(): ?object
    {
        if (! function_exists('auth')) {
            return null;
        }

        try {
            return auth()->user();
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Check if the user is a Super Admin (can see all branches).
     */
    protected function isSuperAdmin(?object $user): bool
    {
        if (! $user) {
            return false;
        }

        // Check using spatie/laravel-permission's hasAnyRole method
        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole(['Super Admin', 'super-admin']);
        }

        return false;
    }

    /**
     * Get all branch IDs the user has access to.
     *
     * @return array<int>
     */
    protected function getAccessibleBranchIds(?object $user, ?int $primaryBranchId): array
    {
        $branchIds = [];

        // Add primary branch
        if ($primaryBranchId !== null) {
            $branchIds[] = $primaryBranchId;
        }

        // Add additional branches from relationship
        if ($user && method_exists($user, 'branches')) {
            try {
                if (! $user->relationLoaded('branches')) {
                    $user->load('branches');
                }
                $additionalBranches = $user->branches->pluck('id')->toArray();
                $branchIds = array_unique(array_merge($branchIds, $additionalBranches));
            } catch (\Exception) {
                // Ignore relationship loading errors
            }
        }

        return array_values(array_filter($branchIds));
    }
}
