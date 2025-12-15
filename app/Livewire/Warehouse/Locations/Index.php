<?php

declare(strict_types=1);

namespace App\Livewire\Warehouse\Locations;

use App\Models\Warehouse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $type = 'main';

    public string $status = 'active';

    public string $address = '';

    public string $notes = '';

    public function mount(): void
    {
        $this->authorize('warehouse.view');
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

    public function openModal(?int $id = null): void
    {
        $this->authorize('warehouse.manage');

        $this->resetForm();

        if ($id) {
            $warehouse = Warehouse::findOrFail($id);
            
            $user = auth()->user();
            if ($user->branch_id && $warehouse->branch_id !== $user->branch_id) {
                abort(403);
            }

            $this->editingId = $id;
            $this->code = $warehouse->code;
            $this->name = $warehouse->name;
            $this->type = $warehouse->type ?? 'main';
            $this->status = $warehouse->status;
            $this->address = $warehouse->address ?? '';
            $this->notes = $warehouse->notes ?? '';
        }

        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->code = '';
        $this->name = '';
        $this->type = 'main';
        $this->status = 'active';
        $this->address = '';
        $this->notes = '';
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $this->authorize('warehouse.manage');

        $rules = [
            'name' => 'required|string|max:255',
            'type' => 'required|in:main,secondary,virtual',
            'status' => 'required|in:active,inactive',
            'address' => 'nullable|string|max:500',
            'notes' => 'nullable|string',
        ];

        if ($this->editingId) {
            $rules['code'] = 'required|string|max:50|unique:warehouses,code,' . $this->editingId;
        } else {
            $rules['code'] = 'nullable|string|max:50|unique:warehouses,code';
        }

        $validated = $this->validate($rules);

        $user = auth()->user();
        $data = array_merge($validated, [
            'branch_id' => $user->branch_id ?? 1,
            'created_by' => $this->editingId ? null : $user->id,
            'updated_by' => $user->id,
        ]);

        // Remove null created_by on update
        if ($this->editingId) {
            unset($data['created_by']);
        }

        if ($this->editingId) {
            Warehouse::findOrFail($this->editingId)->update($data);
            session()->flash('success', __('Warehouse updated successfully'));
        } else {
            Warehouse::create($data);
            session()->flash('success', __('Warehouse created successfully'));
        }

        Cache::forget('warehouses_stats_' . ($user->branch_id ?? 'all'));
        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $this->authorize('warehouse.manage');

        $warehouse = Warehouse::findOrFail($id);
        
        $user = auth()->user();
        if ($user->branch_id && $warehouse->branch_id !== $user->branch_id) {
            abort(403);
        }

        $warehouse->delete();
        Cache::forget('warehouses_stats_' . ($user->branch_id ?? 'all'));
        session()->flash('success', __('Warehouse deleted successfully'));
    }

    public function render()
    {
        $user = auth()->user();

        $warehouses = Warehouse::query()
            ->withCount('stockMovements')
            ->when($user->branch_id, fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(15);

        $stats = [
            'total' => Warehouse::when($user->branch_id, fn ($q) => $q->where('branch_id', $user->branch_id))->count(),
            'active' => Warehouse::when($user->branch_id, fn ($q) => $q->where('branch_id', $user->branch_id))
                ->where('status', 'active')->count(),
            'inactive' => Warehouse::when($user->branch_id, fn ($q) => $q->where('branch_id', $user->branch_id))
                ->where('status', 'inactive')->count(),
        ];

        return view('livewire.warehouse.locations.index', [
            'warehouses' => $warehouses,
            'stats' => $stats,
        ]);
    }
}
