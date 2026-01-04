<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Roles;

use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public string $sortField = 'name';

    public string $sortDirection = 'asc';

    public function mount(): void
    {
        // Authorization check - must have roles.manage permission
        $user = auth()->user();
        if (! $user || ! $user->can('roles.manage')) {
            abort(403, __('Unauthorized access to role management'));
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

    public function delete(int $id): void
    {
        $this->authorize('roles.manage');

        $role = Role::findOrFail($id);

        if ($role->name === 'Super Admin') {
            session()->flash('error', __('Cannot delete Super Admin role'));

            return;
        }

        $role->delete();
        session()->flash('success', __('Role deleted successfully'));
    }

    public function render()
    {
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->withCount('permissions', 'users')
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(15);

        return view('livewire.admin.roles.index', [
            'roles' => $roles,
        ])->layout('layouts.app', ['title' => __('Role Management')]);
    }
}
