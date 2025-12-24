<?php

declare(strict_types=1);

namespace App\Livewire\Sales;

use App\Models\Sale;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[Layout('layouts.app')]
class Show extends Component
{
    public Sale $sale;

    public function mount(Sale $sale): void
    {
        $user = auth()->user()?->fresh();
        if (! $user?->can('sales.view')) {
            throw new HttpException(403);
        }

        $branchId = $user?->branch_id;

        if ($branchId === null) {
            if (app()->runningUnitTests()) {
                \Log::debug('sale-branch-missing', ['user_id' => $user?->id]);
            }
            throw new HttpException(403, __('You must be assigned to a branch to view sales.'));
        }

        if ((int) $branchId !== (int) $sale->branch_id) {
            if (app()->runningUnitTests()) {
                \Log::debug('sale-branch-mismatch', [
                    'user_branch' => $branchId,
                    'sale_branch' => $sale->branch_id,
                ]);
            }
            throw new HttpException(403);
        }

        $this->sale = $sale->load(['items.product', 'customer', 'branch', 'payments']);
    }

    public function render()
    {
        return view('livewire.sales.show');
    }
}
