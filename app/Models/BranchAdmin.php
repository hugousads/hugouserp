<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchAdmin extends Model
{
    use HasFactory;

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

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePrimary(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_primary', true);
    }

    public function scopeForBranch($query, $branchId)
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
