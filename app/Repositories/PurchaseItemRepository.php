<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\PurchaseItem;
use App\Repositories\Contracts\PurchaseItemRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class PurchaseItemRepository extends EloquentBaseRepository implements PurchaseItemRepositoryInterface
{
    public function __construct(PurchaseItem $model)
    {
        parent::__construct($model);
    }

    /**
     * Filter purchase items by branch through the purchase relationship.
     * Note: purchase_items table doesn't have branch_id - filter via purchase.
     */
    protected function baseBranchQuery(int $branchId): Builder
    {
        return $this->query()
            ->whereHas('purchase', fn($q) => $q->where('branch_id', $branchId));
    }

    public function paginateForBranch(int $branchId, int $perPage = 20): LengthAwarePaginator
    {
        return $this->baseBranchQuery($branchId)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function forPurchase(int $purchaseId): \Illuminate\Database\Eloquent\Collection
    {
        return $this->query()
            ->where('purchase_id', $purchaseId)
            ->with('product')
            ->get();
    }
}
