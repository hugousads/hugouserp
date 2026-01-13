<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AuditLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'performed_by_id',
        'impersonating_as_id',
        'target_user_id',
        'branch_id',
        'module_key',
        'action',
        'subject_type',
        'subject_id',
        'auditable_type',
        'auditable_id',
        'ip',
        'user_agent',
        'old_values',
        'new_values',
        'meta',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (AuditLog $log): void {
            if ($log->subject_type && ! $log->auditable_type) {
                $log->auditable_type = $log->subject_type;
            } elseif ($log->auditable_type && ! $log->subject_type) {
                $log->subject_type = $log->auditable_type;
            }

            if ($log->subject_id && ! $log->auditable_id) {
                $log->auditable_id = $log->subject_id;
            } elseif ($log->auditable_id && ! $log->subject_id) {
                $log->subject_id = $log->auditable_id;
            }

            if (! $log->action) {
                $log->action = 'unknown';
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The user who actually performed the action (may be different from user_id during impersonation).
     */
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_id');
    }

    /**
     * The user being impersonated when this action was performed (if any).
     */
    public function impersonatingAs(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonating_as_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Check if this action was performed during an impersonation session.
     */
    public function wasImpersonated(): bool
    {
        return $this->impersonating_as_id !== null;
    }

    /**
     * Scope query to actions performed during impersonation.
     */
    public function scopeImpersonated(Builder $query): Builder
    {
        return $query->whereNotNull('impersonating_as_id');
    }

    /**
     * Scope query to actions performed by a specific actual user (including impersonated actions).
     */
    public function scopePerformedBy(Builder $query, int $userId): Builder
    {
        return $query->where('performed_by_id', $userId);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeForModule(Builder $query, string $moduleKey): Builder
    {
        return $query->where('module_key', $moduleKey);
    }

    public function scopeForSubject(Builder $query, string $type, int $id): Builder
    {
        return $query->where(function ($q) use ($type) {
            $q->where('subject_type', $type)
                ->orWhere('auditable_type', $type);
        })->where(function ($q) use ($id) {
            $q->where('subject_id', $id)
                ->orWhere('auditable_id', $id);
        });
    }

    public function scopeAction(Builder $query, string $action): Builder
    {
        return $query->where('action', 'like', "%{$action}%");
    }

    public function scopeCreatedBetween(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    public function getChangedFieldsAttribute(): array
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];

        return array_unique(array_merge(array_keys($old), array_keys($new)));
    }

    public function getFormattedActionAttribute(): string
    {
        $parts = explode(':', $this->action);

        return count($parts) === 2
            ? __($parts[0]).' - '.__($parts[1])
            : __($this->action);
    }

    public function getUserAgentSummaryAttribute(): array
    {
        $ua = $this->user_agent ?? '';

        $browser = 'Unknown';
        $os = 'Unknown';

        if (str_contains($ua, 'Chrome')) {
            $browser = 'Chrome';
        } elseif (str_contains($ua, 'Firefox')) {
            $browser = 'Firefox';
        } elseif (str_contains($ua, 'Safari')) {
            $browser = 'Safari';
        } elseif (str_contains($ua, 'Edge')) {
            $browser = 'Edge';
        }

        if (str_contains($ua, 'Windows')) {
            $os = 'Windows';
        } elseif (str_contains($ua, 'Mac')) {
            $os = 'macOS';
        } elseif (str_contains($ua, 'Linux')) {
            $os = 'Linux';
        } elseif (str_contains($ua, 'Android')) {
            $os = 'Android';
        } elseif (str_contains($ua, 'iOS') || str_contains($ua, 'iPhone')) {
            $os = 'iOS';
        }

        return [
            'browser' => $browser,
            'os' => $os,
            'full' => $ua,
        ];
    }
}
