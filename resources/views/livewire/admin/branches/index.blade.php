{{-- resources/views/livewire/admin/branches/index.blade.php --}}
<div class="space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
            <h1 class="text-lg font-semibold text-slate-800">
                {{ __('Branches') }}
            </h1>
            <p class="text-sm text-slate-500">
                {{ __('List of branches in the system.') }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <div class="w-full sm:w-64">
                <input type="search" wire:model.live.debounce.500ms="search"
                       placeholder="{{ __('Search branches...') }}"
                       class="erp-input rounded-full">
            </div>
            <a href="{{ route('admin.branches.create') }}"
               class="erp-btn-primary text-xs px-3 py-2">
                {{ __('Add branch') }}
            </a>
        </div>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-slate-200 shadow-sm shadow-emerald-500/10">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-3 py-2 text-start text-xs font-semibold text-slate-500">
                        #
                    </th>
                    <th class="px-3 py-2 text-start text-xs font-semibold text-slate-500">
                        {{ __('Name') }}
                    </th>
                    <th class="px-3 py-2 text-start text-xs font-semibold text-slate-500">
                        {{ __('Code') }}
                    </th>
                    <th class="px-3 py-2 text-start text-xs font-semibold text-slate-500">
                        {{ __('Main') }}
                    </th>
                    <th class="px-3 py-2 text-start text-xs font-semibold text-slate-500">
                        {{ __('Active') }}
                    </th>
                    <th class="px-3 py-2 text-end text-xs font-semibold text-slate-500">
                        {{ __('Actions') }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @forelse ($branches as $branch)
                    <tr class="hover:bg-emerald-50/40">
                        <td class="px-3 py-2 text-xs text-slate-500">
                            {{ $branch->id }}
                        </td>
                        <td class="px-3 py-2 text-xs text-slate-800">
                            {{ $branch->name }}
                        </td>
                        <td class="px-3 py-2 text-xs text-slate-700">
                            {{ $branch->code ?? '' }}
                        </td>
                        <td class="px-3 py-2 text-xs text-slate-700">
                            @if(isset($branch->is_main) && $branch->is_main)
                                <span class="erp-badge">
                                    {{ __('Main') }}
                                </span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-xs text-slate-700">
                            @if(isset($branch->is_active) && $branch->is_active)
                                <span class="erp-badge">
                                    {{ __('Active') }}
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[0.7rem] font-medium text-slate-600">
                                    {{ __('Inactive') }}
                                </span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-xs text-end">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('admin.branches.modules', $branch->id) }}"
                                   class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-[0.7rem] font-semibold text-blue-700 hover:bg-blue-100"
                                   title="{{ __('Manage Modules') }}">
                                    <svg class="w-3.5 h-3.5 me-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                    </svg>
                                    {{ __('Modules') }}
                                </a>
                                <a href="{{ route('admin.branches.edit', $branch->id) }}"
                                   class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-1 text-[0.7rem] font-semibold text-emerald-700 hover:bg-emerald-100">
                                    {{ __('Edit') }}
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-4 text-center text-xs text-slate-500">
                            {{ __('No branches found.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $branches->links() }}
    </div>
</div>
