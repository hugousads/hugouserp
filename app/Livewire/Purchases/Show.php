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
        $user = auth()->user();
        throw_if(!$user || !$user->can('purchases.view'), new HttpException(403));

        $branchId = $user->branch_id;
        $isSuperAdmin = (bool) $user->hasRole('super-admin');

        throw_if(!$isSuperAdmin && !$branchId, new HttpException(403, __('You must be assigned to a branch to view purchases.')));
        throw_if(!$isSuperAdmin && (int) $branchId !== (int) $purchase->branch_id, new HttpException(403));

        $this->purchase = $purchase->load(['items.product', 'supplier', 'branch']);
    }

    public function render()
    {
        return view('livewire.purchases.show');
    }
}
