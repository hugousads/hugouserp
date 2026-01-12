<?php

namespace App\Models;

use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BranchAdmin - Pivot model for branch administrators
 *
 * IMPORTANT: This model is explicitly excluded from BranchScope to prevent
 * infinite recursion during authentication. BranchAdmin records are used
 * to determine user permissions, so they must be accessible regardless of
 * current branch context.
 *
 * The exclusion is handled in BranchScope::shouldExcludeModel() method.
 */
class BranchAdmin extends BaseModel
{
    protected $fillable = [
        'branch_id',
        'user_id',
        'can_manage_users',
        'can_manage_roles',
        'can_view_reports',
        'can_export_data',
        'can_manage_settings',
        'is_primary',
        'is_active',
    ];

    protected $casts = [
        'can_manage_users' => 'boolean',
        'can_manage_roles' => 'boolean',
        'can_view_reports' => 'boolean',
        'can_export_data' => 'boolean',
        'can_manage_settings' => 'boolean',
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    public function scopeForBranch(Builder $query, $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    public function canManageUsersInBranch(): bool
    {
        return $this->is_active && $this->can_manage_users;
    }

    public function canViewReportsInBranch(): bool
    {
        return $this->is_active && $this->can_view_reports;
    }

    public function canExportDataFromBranch(): bool
    {
        return $this->is_active && $this->can_export_data;
    }
}
