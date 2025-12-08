<?php

declare(strict_types=1);

namespace App\Livewire\Purchases\Requisitions;

use App\Models\PurchaseRequisition;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $priority = '';

    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';

    public function mount(): void
    {
        $this->authorize('purchases.requisitions.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function getStatistics(): array
    {
        $branchId = auth()->user()->branch_id;

        $stats = PurchaseRequisition::where('branch_id', $branchId)
            ->selectRaw('
                COUNT(*) as total_requisitions,
                COUNT(CASE WHEN status = ? THEN 1 END) as pending_approval,
                COUNT(CASE WHEN status = ? THEN 1 END) as approved,
                COUNT(CASE WHEN status = ? THEN 1 END) as converted_to_po
            ', ['pending_approval', 'approved', 'converted'])
            ->first();

        return [
            'total_requisitions' => $stats->total_requisitions ?? 0,
            'pending_approval' => $stats->pending_approval ?? 0,
            'approved' => $stats->approved ?? 0,
            'converted_to_po' => $stats->converted_to_po ?? 0,
        ];
    }

    public function approve(int $id): void
    {
        $this->authorize('purchases.requisitions.approve');

        $requisition = PurchaseRequisition::findOrFail($id);
        $requisition->approve();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __('Purchase requisition approved successfully'),
        ]);
    }

    public function reject(int $id, string $reason = ''): void
    {
        $this->authorize('purchases.requisitions.approve');

        $requisition = PurchaseRequisition::findOrFail($id);
        $requisition->reject($reason);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __('Purchase requisition rejected'),
        ]);
    }

    public function delete(int $id): void
    {
        $this->authorize('purchases.requisitions.create');

        $requisition = PurchaseRequisition::findOrFail($id);
        $requisition->delete();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __('Purchase requisition deleted successfully'),
        ]);
    }

    #[Layout('layouts.app')]
    public function render()
    {
        $branchId = auth()->user()->branch_id;

        $query = PurchaseRequisition::with(['employee', 'department', 'items'])
            ->where('branch_id', $branchId);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('requisition_code', 'like', "%{$this->search}%")
                    ->orWhere('subject', 'like', "%{$this->search}%")
                    ->orWhereHas('employee', function ($q) {
                        $q->where('name', 'like', "%{$this->search}%");
                    });
            });
        }

        if ($this->status) {
            $query->where('status', $this->status);
        }

        if ($this->priority) {
            $query->where('priority', $this->priority);
        }

        $query->orderBy($this->sortField, $this->sortDirection);

        $requisitions = $query->paginate(15);
        $statistics = $this->getStatistics();

        return view('livewire.purchases.requisitions.index', [
            'requisitions' => $requisitions,
            'statistics' => $statistics,
        ]);
    }
}
