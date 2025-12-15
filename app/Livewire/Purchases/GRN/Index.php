<?php

namespace App\Livewire\Purchases\GRN;

use App\Models\GoodsReceivedNote;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use AuthorizesRequests, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';

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

    public function approve(int $id): void
    {
        $this->authorize('grn.approve');

        $grn = GoodsReceivedNote::findOrFail($id);
        $grn->update(['status' => 'approved']);

        session()->flash('success', __('GRN approved successfully.'));
    }

    public function reject(int $id, string $reason = ''): void
    {
        $this->authorize('grn.reject');

        $grn = GoodsReceivedNote::findOrFail($id);
        $grn->update([
            'status' => 'rejected',
            'rejection_reason' => $reason
        ]);

        session()->flash('success', __('GRN rejected successfully.'));
    }

    public function markComplete(int $id): void
    {
        $this->authorize('grn.update');

        $grn = GoodsReceivedNote::findOrFail($id);
        $grn->update(['status' => 'complete']);

        session()->flash('success', __('GRN marked as complete.'));
    }

    public function delete(int $id): void
    {
        $this->authorize('grn.delete');

        GoodsReceivedNote::findOrFail($id)->delete();

        session()->flash('success', __('GRN deleted successfully.'));
    }

    public function render()
    {
        $query = GoodsReceivedNote::query()
            ->with(['purchase', 'supplier', 'inspectedBy'])
            ->when($this->search, function ($q) {
                $q->where(function ($query) {
                    $query->where('code', 'like', "%{$this->search}%")
                        ->orWhereHas('purchase', function ($q) {
                            $q->where('invoice_number', 'like', "%{$this->search}%");
                        })
                        ->orWhereHas('supplier', function ($q) {
                            $q->where('name', 'like', "%{$this->search}%");
                        });
                });
            })
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->orderBy($this->sortField, $this->sortDirection);

        $grns = $query->paginate(15);

        // Calculate statistics
        $stats = [
            'total' => GoodsReceivedNote::count(),
            'pending' => GoodsReceivedNote::where('status', 'pending')->count(),
            'approved' => GoodsReceivedNote::where('status', 'approved')->count(),
            'rejected' => GoodsReceivedNote::where('status', 'rejected')->count(),
            'partial' => GoodsReceivedNote::where('status', 'partial')->count(),
            'complete' => GoodsReceivedNote::where('status', 'complete')->count(),
        ];

        return view('livewire.purchases.grn.index', [
            'grns' => $grns,
            'stats' => $stats,
        ]);
    }
}
