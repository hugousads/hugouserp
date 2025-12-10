<?php

declare(strict_types=1);

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;

trait Searchable
{
    public function scopeSearch(Builder $query, ?string $search, ?array $columns = null): Builder
    {
        if (empty($search)) {
            return $query;
        }

        $searchColumns = $columns ?? $this->getSearchableColumns();

        if (empty($searchColumns)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search, $searchColumns) {
            foreach ($searchColumns as $column) {
                if (str_contains($column, '.')) {
                    [$relation, $relationColumn] = explode('.', $column, 2);
                    $q->orWhereHas($relation, function (Builder $relationQuery) use ($search, $relationColumn) {
                        $relationQuery->where($relationColumn, 'like', "%{$search}%");
                    });
                } else {
                    $q->orWhere($column, 'like', "%{$search}%");
                }
            }
        });
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        foreach ($filters as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (is_array($value)) {
                $query->whereIn($field, $value);
            } elseif (str_starts_with($field, 'min_')) {
                $actualField = substr($field, 4);
                $query->where($actualField, '>=', $value);
            } elseif (str_starts_with($field, 'max_')) {
                $actualField = substr($field, 4);
                $query->where($actualField, '<=', $value);
            } elseif (str_starts_with($field, 'date_from_')) {
                $actualField = substr($field, 10);
                $query->whereDate($actualField, '>=', $value);
            } elseif (str_starts_with($field, 'date_to_')) {
                $actualField = substr($field, 8);
                $query->whereDate($actualField, '<=', $value);
            } elseif (is_bool($value) || in_array($value, ['true', 'false', '1', '0'])) {
                $boolValue = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                $query->where($field, $boolValue);
            } else {
                $query->where($field, $value);
            }
        }

        return $query;
    }

    public function scopeSort(Builder $query, ?string $sortField = null, string $sortDirection = 'asc'): Builder
    {
        $sortField = $sortField ?? $this->getDefaultSortField();
        $sortDirection = strtolower($sortDirection) === 'desc' ? 'desc' : 'asc';

        $allowedSortFields = $this->getSortableColumns();

        if (! in_array($sortField, $allowedSortFields)) {
            $sortField = $this->getDefaultSortField();
        }

        return $query->orderBy($sortField, $sortDirection);
    }

    protected function getSearchableColumns(): array
    {
        return property_exists($this, 'searchable') ? $this->searchable : ['name'];
    }

    protected function getSortableColumns(): array
    {
        return property_exists($this, 'sortable') ? $this->sortable : ['id', 'name', 'created_at', 'updated_at'];
    }

    protected function getDefaultSortField(): string
    {
        return property_exists($this, 'defaultSort') ? $this->defaultSort : 'created_at';
    }
}
