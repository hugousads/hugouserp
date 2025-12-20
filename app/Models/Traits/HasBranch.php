<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasBranch
{
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeForCurrentBranch(Builder $query, ?object $user = null): Builder
    {
        $user = $user ?? $this->resolveCurrentUser();
        $branchId = $user?->branch_id;

        if ($branchId) {
            return $query->where('branch_id', $branchId);
        }

        return $query;
    }

    public function scopeForUserBranches(Builder $query, ?object $user = null): Builder
    {
        $user = $user ?? $this->resolveCurrentUser();

        if (! $user) {
            // BUG FIX: Instead of whereRaw('1 = 0'), use whereNull which is more efficient
            return $query->whereNull($this->getTable() . '.id');
        }

        $branchIds = [];

        if (method_exists($user, 'branches')) {
            if (! $user->relationLoaded('branches')) {
                $user->load('branches');
            }

            $branchIds = $user->branches->pluck('id')->toArray();
        }

        if ($user->branch_id && ! in_array($user->branch_id, $branchIds)) {
            $branchIds[] = $user->branch_id;
        }

        return empty($branchIds) ? $query->whereNull($this->getTable() . '.id') : $query->whereIn('branch_id', $branchIds);
    }

    protected function resolveCurrentUser(): ?object
    {
        if (! function_exists('auth')) {
            return null;
        }

        try {
            return auth()->user();
        } catch (\Exception $e) {
            return null;
        }
    }

    public function belongsToBranch(int $branchId): bool
    {
        return $this->branch_id === $branchId;
    }

    public function isAccessibleByUser(?object $user = null): bool
    {
        $user = $user ?? $this->resolveCurrentUser();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('Super Admin')) {
            return true;
        }

        if ($this->branch_id === $user->branch_id) {
            return true;
        }

        if (method_exists($user, 'branches') && $user->relationLoaded('branches')) {
            return $user->branches->contains('id', $this->branch_id);
        }

        return false;
    }
}
