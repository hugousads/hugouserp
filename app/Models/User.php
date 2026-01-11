<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends BaseModel implements AuthenticatableContract, AuthorizableContract
{
    use AuthenticatableTrait;
    use Authorizable;
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use LogsActivity;
    use Notifiable;
    use SoftDeletes;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'username',
        'locale',
        'timezone',
        'branch_id',
        'avatar',
        'preferences',
        // Admin-controllable fields - MUST be validated at controller/request level
        // These fields have security implications and should only be modified by authorized users
        'is_active',
        'last_login_at',
        'max_discount_percent',
        'daily_discount_limit',
        'can_modify_price',
        'max_sessions',
    ];

    // Note: The following fields are NOT in $fillable for security:
    // - id, remember_token, two_factor_*, password_changed_at,
    // - last_login_ip, failed_login_attempts, locked_until, email_verified_at
    // These should only be modified through specific, controlled methods

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'bool',
        'last_login_at' => 'datetime',
        'two_factor_enabled' => 'bool',
        'two_factor_confirmed_at' => 'datetime',
        'can_modify_price' => 'bool',
        'password_changed_at' => 'datetime',
        'preferences' => 'array',
        'password' => 'hashed',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_user');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    /**
     * Get the HR employee record linked to this user
     */
    public function hrEmployee(): HasOne
    {
        return $this->hasOne(HREmployee::class, 'user_id');
    }

    /**
     * Get the employee ID for self-service features
     * Returns the ID of the linked HREmployee record
     * Note: For bulk operations, eager load 'hrEmployee' to avoid N+1 queries
     */
    public function getEmployeeIdAttribute(): ?int
    {
        // Check if already loaded to avoid extra queries
        if (! $this->relationLoaded('hrEmployee')) {
            $this->load('hrEmployee');
        }

        return $this->hrEmployee?->id;
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_enabled && $this->two_factor_confirmed_at !== null;
    }

    public function routeNotificationForBroadcast()
    {
        return 'private-App.Models.User.'.$this->id;
    }

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }

    public function shouldReceiveBroadcastNotifications(): bool
    {
        return $this->is_active && $this->email_verified_at !== null;
    }

    public function getUnreadNotificationsCountAttribute(): int
    {
        return $this->unreadNotifications()->count();
    }

    public static function findByCredential(string $credential): ?self
    {
        return static::where('email', $credential)
            ->orWhere('phone', $credential)
            ->orWhere('username', $credential)
            ->first();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'phone', 'is_active', 'locale', 'timezone', 'branch_id', 'two_factor_enabled'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Check if user is a branch admin for a specific branch
     */
    public function isBranchAdmin(?int $branchId = null): bool
    {
        $branchId = $branchId ?? $this->branch_id;

        if (! $branchId) {
            return false;
        }

        return BranchAdmin::where('user_id', $this->id)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Get branch admin record for this user
     */
    public function getBranchAdminRecord(?int $branchId = null): ?BranchAdmin
    {
        $branchId = $branchId ?? $this->branch_id;

        if (! $branchId) {
            return null;
        }

        return BranchAdmin::where('user_id', $this->id)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Check if user can manage users in their branch
     */
    public function canManageBranchUsers(): bool
    {
        if ($this->hasRole('Super Admin')) {
            return true;
        }

        $branchAdmin = $this->getBranchAdminRecord();

        return $branchAdmin?->can_manage_users ?? false;
    }

    /**
     * Check if user can view reports in their branch
     */
    public function canViewBranchReports(): bool
    {
        if ($this->hasRole('Super Admin')) {
            return true;
        }

        $branchAdmin = $this->getBranchAdminRecord();

        return $branchAdmin?->can_view_reports ?? $this->can('branch.reports.view');
    }

    /**
     * Check if user can manage branch settings
     */
    public function canManageBranchSettings(): bool
    {
        if ($this->hasRole('Super Admin')) {
            return true;
        }

        $branchAdmin = $this->getBranchAdminRecord();

        return $branchAdmin?->can_manage_settings ?? false;
    }

    /**
     * Get current branch for the user
     */
    public function getCurrentBranch(): ?Branch
    {
        // Check session for admin branch context first
        $contextBranchId = session('admin_branch_context');

        if ($contextBranchId && $this->hasRole('Super Admin')) {
            return Branch::find($contextBranchId);
        }

        return $this->branch;
    }

    /**
     * Check if user is a branch employee (not admin or manager)
     */
    public function isBranchEmployee(): bool
    {
        return $this->hasRole('Branch Employee') ||
               $this->hasRole('Branch Cashier');
    }

    /**
     * Check if user is a branch supervisor
     */
    public function isBranchSupervisor(): bool
    {
        return $this->hasRole('Branch Supervisor') ||
               $this->hasRole('Branch Manager');
    }
}
