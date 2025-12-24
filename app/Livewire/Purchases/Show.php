<?php

declare(strict_types=1);

namespace App\Livewire\Purchases;

use App\Models\Purchase;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[Layout('layouts.app')]
class Show extends Component
{
    public Purchase $purchase;

    public function mount(Purchase $purchase): void
    {
        $user = auth()->user()?->fresh();
        if (! $user?->can('purchases.view')) {
            throw new HttpException(403);
        }

        $branchId = $user?->branch_id;

        if ($branchId === null) {
            if (app()->runningUnitTests()) {
                \Log::debug('purchase-branch-missing', ['user_id' => $user?->id]);
            }
            throw new HttpException(403, __('You must be assigned to a branch to view purchases.'));
        }

        if ((int) $branchId !== (int) $purchase->branch_id) {
            if (app()->runningUnitTests()) {
                \Log::debug('purchase-branch-mismatch', [
                    'user_branch' => $branchId,
                    'purchase_branch' => $purchase->branch_id,
                ]);
            }
            throw new HttpException(403);
        }

        $this->purchase = $purchase->load(['items.product', 'supplier', 'branch']);
    }

    public function render()
    {
        return view('livewire.purchases.show');
    }
}
