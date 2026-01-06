<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\SaleItem;
use App\Repositories\Contracts\SaleItemRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class SaleItemRepository extends EloquentBaseRepository implements SaleItemRepositoryInterface
{
    public function __construct(SaleItem $model)
    {
        parent::__construct($model);
    }

    /**
     * Filter sale items by branch through the sale relationship.
     * Note: sale_items table doesn't have branch_id - filter via sale.
     */
    protected function baseBranchQuery(int $branchId): Builder
    {
        return $this->query()
            ->whereHas('sale', fn($q) => $q->where('branch_id', $branchId));
    }

    public function paginateForBranch(int $branchId, int $perPage = 20): LengthAwarePaginator
    {
        return $this->baseBranchQuery($branchId)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function forSale(int $saleId): \Illuminate\Database\Eloquent\Collection
    {
        return $this->query()
            ->where('sale_id', $saleId)
            ->with('product')
            ->get();
    }
}
