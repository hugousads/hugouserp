<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Roles;

use App\Http\Requests\Traits\HasMultilingualValidation;
use App\Livewire\Concerns\HandlesErrors;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class Form extends Component
{
    use HandlesErrors;
    use HasMultilingualValidation;

    public ?Role $role = null;

    public bool $editMode = false;

    public string $name = '';

    public array $selectedPermissions = [];

    protected function rules(): array
    {
        $unique = $this->editMode ? '|unique:roles,name,'.$this->role->id : '|unique:roles,name';

        return [
            'name' => 'required|string|max:255'.$unique,
            'selectedPermissions' => 'array',
        ];
    }

    public function mount(?Role $role = null): void
    {
        // Authorization check - must have roles.manage permission
        $user = auth()->user();
        if (! $user || ! $user->can('roles.manage')) {
            abort(403, __('Unauthorized access to role management'));
        }

        if ($role && $role->exists) {
            $this->role = $role;
            $this->editMode = true;
            $this->name = $role->name;
            $this->selectedPermissions = $role->permissions->pluck('id')->map(fn ($id) => (string) $id)->toArray();
        }
    }

    public function save(): mixed
    {
        $validated = $this->validate();
        $editMode = $this->editMode;
        $role = $this->role;
        $selectedPermissions = $this->selectedPermissions;

        if ($editMode && $role->name === 'Super Admin') {
            session()->flash('error', __('Cannot modify Super Admin role'));

            return null;
        }

        return $this->handleOperation(
            operation: function () use ($validated, $editMode, $role, $selectedPermissions) {
                if ($editMode) {
                    $role->update(['name' => $validated['name']]);
                    $role->syncPermissions(Permission::whereIn('id', $selectedPermissions)->get());
                } else {
                    $newRole = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);
                    $newRole->syncPermissions(Permission::whereIn('id', $selectedPermissions)->get());
                }
            },
            successMessage: $editMode ? __('Role updated successfully') : __('Role created successfully'),
            redirectRoute: 'admin.roles.index'
        );
    }

    public function render()
    {
        $permissions = Permission::where('guard_name', 'web')
            ->orderBy('name')
            ->get()
            ->groupBy(fn ($p) => explode('.', $p->name)[0] ?? 'general');

        return view('livewire.admin.roles.form', [
            'permissions' => $permissions,
        ])->layout('layouts.app', ['title' => $this->editMode ? __('Edit Role') : __('Add Role')]);
    }
}
