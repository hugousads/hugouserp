<?php

declare(strict_types=1);

namespace App\Livewire\Purchases;

use App\Models\Purchase;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Purchase $purchase;

    public function mount(Purchase $purchase): void
    {
        $this->authorize('purchases.view');
        $user = auth()->user();
        $branchId = $user?->branch_id;
        $isSuperAdmin = (bool) $user?->hasRole('super-admin');

        if (!$isSuperAdmin && !$branchId) {
            abort(403, __('You must be assigned to a branch to view purchases.'));
        }

        if (!$isSuperAdmin && $branchId !== $purchase->branch_id) {
            abort(403);
        }

        $this->purchase = $purchase->load(['items.product', 'supplier', 'branch']);
    }

    public function render()
    {
        return view('livewire.purchases.show');
    }
}
