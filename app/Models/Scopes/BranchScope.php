<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Services\BranchContextManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * BranchScope - Global scope for multi-tenancy branch isolation
 *
 * This scope automatically filters queries by the authenticated user's branch_id,
 * ensuring data isolation between branches. Super Admins can bypass this filter.
 *
 * IMPORTANT: Uses BranchContextManager to prevent infinite recursion with Auth
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

        // CRITICAL: Prevent infinite recursion during authentication
        // When auth is being resolved, don't apply scope
        if (BranchContextManager::isResolvingAuth()) {
            return;
        }

        // Skip if the model doesn't have a branch_id column
        if (! $this->hasBranchIdColumn($model)) {
            return;
        }

        // Skip for models that should never be scoped by branch
        if ($this->shouldExcludeModel($model)) {
            return;
        }

        // Get current user safely through BranchContextManager
        $user = BranchContextManager::getCurrentUser();

        // Skip if no authenticated user
        if (! $user) {
            return;
        }

        // Skip scope for Super Admins (they can see all branches)
        if (BranchContextManager::isSuperAdmin($user)) {
            return;
        }

        // Get accessible branch IDs from context manager
        $accessibleBranchIds = BranchContextManager::getAccessibleBranchIds();

        // Apply the branch filter
        $table = $model->getTable();

        if (count($accessibleBranchIds) === 1) {
            $builder->where("{$table}.branch_id", $accessibleBranchIds[0]);
        } elseif (count($accessibleBranchIds) > 1) {
            $builder->whereIn("{$table}.branch_id", $accessibleBranchIds);
        } elseif (count($accessibleBranchIds) === 0) {
            // User has no branch access - return empty result set
            // Using a condition that's always false in a database-agnostic way
            $builder->whereNull("{$table}.id")->whereNotNull("{$table}.id");
        }
    }

    /**
     * Check if the model should be excluded from branch scoping.
     * Some models should never be filtered by branch.
     */
    protected function shouldExcludeModel(Model $model): bool
    {
        // These models are excluded from branch scoping to prevent recursion
        // and maintain referential integrity
        $excludedModels = [
            \App\Models\User::class,
            \App\Models\Branch::class,
            \App\Models\BranchAdmin::class,
            \App\Models\Module::class,
            \App\Models\Permission::class,
            \App\Models\Role::class,
        ];

        foreach ($excludedModels as $excludedModel) {
            if ($model instanceof $excludedModel) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the model has a branch_id column.
     */
    protected function hasBranchIdColumn(Model $model): bool
    {
        // Check if the model has branch_id in fillable attributes
        $fillable = $model->getFillable();
        if (in_array('branch_id', $fillable, true)) {
            return true;
        }

        // Check if model has a 'branch' relationship method
        // This is a good indicator that the model has branch_id
        if (method_exists($model, 'branch')) {
            return true;
        }

        return false;
    }
}
