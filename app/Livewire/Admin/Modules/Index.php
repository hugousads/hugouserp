<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Modules;

use App\Models\Module;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public string $sortField = 'sort_order';

    public string $sortDirection = 'asc';

    public function mount(): void
    {
        // Authorization check - must have modules.manage permission
        $user = auth()->user();
        if (! $user || ! $user->can('modules.manage')) {
            abort(403, __('Unauthorized access to module management'));
        }
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

    public function toggleActive(int $id): void
    {
        $this->authorize('modules.manage');

        $module = Module::findOrFail($id);

        if ($module->is_core) {
            session()->flash('error', __('Cannot deactivate core module'));

            return;
        }

        $module->update(['is_active' => ! $module->is_active]);
        session()->flash('success', __('Module status updated'));
    }

    public function render()
    {
        $modules = Module::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('key', 'like', "%{$this->search}%"))
            ->withCount('branches')
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(15);

        return view('livewire.admin.modules.index', [
            'modules' => $modules,
        ])->layout('layouts.app', ['title' => __('Module Management')]);
    }
}
