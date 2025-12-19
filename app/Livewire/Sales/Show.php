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
        $user = auth()->user();
        throw_if(!$user?->can('sales.view'), new HttpException(403));

        $branchId = $user->branch_id;
        $isSuperAdmin = (bool) $user->hasRole('super-admin');
        $branchIdInt = $branchId !== null ? (int) $branchId : null;

        throw_if(!$isSuperAdmin && $branchIdInt === null, new HttpException(403, __('You must be assigned to a branch to view sales.')));
        throw_if(!$isSuperAdmin && $branchIdInt !== (int) $sale->branch_id, new HttpException(403));

        $this->sale = $sale->load(['items.product', 'customer', 'branch', 'payments']);
    }

    public function render()
    {
        return view('livewire.sales.show');
    }
}
