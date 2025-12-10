<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Branch;
use App\Repositories\Contracts\BranchRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class BranchRepository extends EloquentBaseRepository implements BranchRepositoryInterface
{
    public function __construct(Branch $model)
    {
        parent::__construct($model);
    }

    public function findByCode(string $code): ?Branch
    {
        return $this->query()->where('code', $code)->first();
    }

    public function getActiveBranches(): Collection
    {
        return $this->query()->where('is_active', true)->orderBy('name')->get();
    }

    public function getBranchesWithModules(): Collection
    {
        return $this->query()->with('modules')->get();
    }

    public function paginateWithFilters(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->query()->with('modules');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        $sortField = $filters['sort_field'] ?? 'name';
        $sortDirection = $filters['sort_direction'] ?? 'asc';
        $query->orderBy($sortField, $sortDirection);

        return $query->paginate($perPage);
    }

    public function syncModules(Branch $branch, array $moduleIds): Branch
    {
        $branch->modules()->sync($moduleIds);

        return $branch->fresh(['modules']);
    }

    public function getEnabledModules(Branch $branch): Collection
    {
        return $branch->modules()->where('is_active', true)->get();
    }

    public function deactivate(Branch $branch): Branch
    {
        $branch->is_active = false;
        $branch->save();

        return $branch;
    }

    public function activate(Branch $branch): Branch
    {
        $branch->is_active = true;
        $branch->save();

        return $branch;
    }

    public function getBranchSettings(Branch $branch): array
    {
        return $branch->settings ?? [];
    }

    public function updateSettings(Branch $branch, array $settings): Branch
    {
        $branch->settings = array_merge($branch->settings ?? [], $settings);
        $branch->save();

        return $branch;
    }
}
