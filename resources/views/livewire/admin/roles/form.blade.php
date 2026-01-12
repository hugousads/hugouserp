<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $editMode ? __('Edit Role') : __('Add Role') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Configure role name and permissions') }}</p>
        </div>
        <a href="{{ route('admin.roles.index') }}" class="erp-btn erp-btn-secondary">{{ __('Back') }}</a>
    </div>

    @if(session()->has('error'))
        <div class="p-3 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 rounded-lg">{{ session('error') }}</div>
    @endif

    <form wire:submit="save" class="erp-card p-6 space-y-6">
        <div>
            <label class="erp-label">{{ __('Role Name') }} <span class="text-red-500">*</span></label>
            <input type="text" wire:model="name" class="erp-input max-w-md @error('name') border-red-500 @enderror">
            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="erp-label mb-4">{{ __('Permissions') }}</label>
            
            {{-- Branch filter section --}}
            <div class="mb-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <div class="flex items-center gap-2">
                        <input type="checkbox" wire:model.live="filterByBranch" id="filterByBranch"
                               class="rounded border-slate-300 dark:border-slate-600 text-emerald-600 focus:ring-emerald-500">
                        <label for="filterByBranch" class="text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ __('Filter by branch modules') }}
                        </label>
                    </div>
                    @if($filterByBranch)
                        <select wire:model.live="filterBranchId" class="erp-input text-sm py-1.5 w-full sm:w-64">
                            <option value="">{{ __('Select a branch...') }}</option>
                            @if(is_array($branches) || is_object($branches))
                                @foreach($branches as $branch)
                                    @if(is_object($branch))
                                        <option value="{{ $branch->id ?? '' }}">{{ $branch->name ?? '' }}</option>
                                    @elseif(is_array($branch))
                                        <option value="{{ $branch['id'] ?? '' }}">{{ $branch['name'] ?? '' }}</option>
                                    @endif
                                @endforeach
                            @endif
                        </select>
                    @endif
                </div>
                @if($filterByBranch && $filterBranchId)
                    <p class="mt-2 text-xs text-blue-600 dark:text-blue-400">
                        <svg class="inline w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ __('Only showing permissions for modules enabled in the selected branch') }}
                    </p>
                @endif
            </div>
            
            {{-- Quick select buttons --}}
            <div class="flex gap-2 mb-4">
                <button type="button" wire:click="selectAllPermissions" 
                        class="text-xs px-3 py-1.5 bg-emerald-100 text-emerald-700 rounded hover:bg-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300">
                    {{ __('Select All') }}
                </button>
                <button type="button" wire:click="clearAllPermissions" 
                        class="text-xs px-3 py-1.5 bg-slate-100 text-slate-700 rounded hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300">
                    {{ __('Clear All') }}
                </button>
            </div>

            <div class="space-y-6">
                @if(is_array($permissions) || is_object($permissions))
                    @foreach($permissions as $group => $groupPermissions)
                        @if($this->isGroupVisible($group))
                        @php
                            $groupLabels = [
                                'dashboard' => __('Dashboard'),
                                'pos' => __('Point of Sale'),
                                'sales' => __('Sales'),
                                'purchases' => __('Purchases'),
                                'customers' => __('Customers'),
                                'suppliers' => __('Suppliers'),
                                'inventory' => __('Inventory'),
                                'warehouse' => __('Warehouse'),
                                'expenses' => __('Expenses'),
                                'income' => __('Income'),
                                'accounting' => __('Accounting'),
                                'banking' => __('Banking'),
                                'hrm' => __('Human Resources'),
                                'hr' => __('Human Resources'),
                                'rental' => __('Rental'),
                                'rentals' => __('Rentals'),
                                'manufacturing' => __('Manufacturing'),
                                'fixed-assets' => __('Fixed Assets'),
                                'projects' => __('Projects'),
                                'documents' => __('Documents'),
                                'helpdesk' => __('Helpdesk'),
                                'tickets' => __('Tickets'),
                                'media' => __('Media Library'),
                                'reports' => __('Reports'),
                                'settings' => __('Settings'),
                                'users' => __('Users'),
                                'roles' => __('Roles'),
                                'branches' => __('Branches'),
                                'branch' => __('Branch'),
                                'modules' => __('Modules'),
                                'stores' => __('Stores'),
                                'store' => __('Store'),
                                'logs' => __('Logs'),
                                'system' => __('System'),
                                'spares' => __('Spare Parts'),
                                'impersonate' => __('Impersonation'),
                            ];
                            $groupLabel = $groupLabels[$group] ?? ucfirst($group);
                        @endphp
                        <div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
                            <div class="bg-slate-50 dark:bg-slate-800 px-4 py-2 font-medium text-slate-700 dark:text-slate-200 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                                <span>{{ $groupLabel }}</span>
                                <span class="text-xs text-slate-500 dark:text-slate-400">({{ $groupPermissions->count() }} {{ __('permissions') }})</span>
                            </div>
                            <div class="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                @foreach($groupPermissions as $permission)
                                    @php
                                        // Get translation from permissions.php lang file
                                        $permLabel = __('permissions.' . $permission->name);
                                        // Fall back to formatted permission name if translation not found
                                        if ($permLabel === 'permissions.' . $permission->name) {
                                            $permLabel = ucwords(str_replace(['.', '-', '_'], ' ', $permission->name));
                                        }
                                    @endphp
                                    <label class="flex items-center gap-2 text-sm cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700/50 p-2 rounded transition-colors">
                                        <input type="checkbox" wire:model="selectedPermissions" value="{{ $permission->id }}" 
                                               class="rounded border-slate-300 dark:border-slate-600 text-emerald-600 focus:ring-emerald-500 dark:bg-slate-700">
                                        <span class="text-slate-700 dark:text-slate-300">{{ $permLabel }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            @endif
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-700">
            <a href="{{ route('admin.roles.index') }}" class="erp-btn erp-btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="erp-btn erp-btn-primary">{{ $editMode ? __('Update') : __('Save') }}</button>
        </div>
    </form>
</div>
