{{-- resources/views/livewire/hrm/employees/index.blade.php --}}
<div class="space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
            <h1 class="text-lg font-semibold text-slate-800 dark:text-slate-100">
                {{ __('Employees') }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ __('HR employees for the current branch.') }}
            </p>
        </div>

        <div class="flex flex-col sm:flex-row items-center gap-2">
            <div class="w-full sm:w-56">
                <input type="search"
                       wire:model.live.debounce.500ms="search"
                       placeholder="{{ __('Search (name, code, position, user)...') }}"
                       class="erp-input rounded-full">
            </div>

            <div class="flex items-center gap-2">
                <select wire:model="status" class="erp-input text-xs w-32">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="active">{{ __('Active') }}</option>
                    <option value="inactive">{{ __('Inactive') }}</option>
                </select>

                <a href="{{ route('app.hrm.employees.create') }}"
                   class="erp-btn-primary text-xs px-3 py-2">
                    {{ __('Add employee') }}
                </a>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/80 shadow-sm shadow-emerald-500/10">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800/80">
                <tr>
                    <th class="px-3 py-2 text-start text-xs font-semibold text-slate-500 dark:text-slate-300">
                        {{ __('Code') }}
                    </th>
                    <th class="px-3 py-2 text-start text-xs font-semibold text-slate-500 dark:text-slate-300">
                        {{ __('Name') }}
                    </th>
                    <th class="px-3 py-2 text-start text-xs font-semibold text-slate-500 dark:text-slate-300">
                        {{ __('Position') }}
                    </th>
                    <th class="px-3 py-2 text-start text-xs font-semibold text-slate-500 dark:text-slate-300">
                        {{ __('Branch') }}
                    </th>
                    <th class="px-3 py-2 text-end text-xs font-semibold text-slate-500 dark:text-slate-300">
                        {{ __('Salary') }}
                    </th>
                    <th class="px-3 py-2 text-center text-xs font-semibold text-slate-500 dark:text-slate-300">
                        {{ __('Status') }}
                    </th>
                    <th class="px-3 py-2 text-start text-xs font-semibold text-slate-500 dark:text-slate-300">
                        {{ __('User') }}
                    </th>
                    <th class="px-3 py-2 text-end text-xs font-semibold text-slate-500 dark:text-slate-300">
                        {{ __('Actions') }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 bg-white/80 dark:bg-slate-900/60">
                @forelse ($employees as $employee)
                    <tr>
                        <td class="px-3 py-2 whitespace-nowrap text-xs text-slate-700 dark:text-slate-200">
                            {{ $employee->code }}
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-xs text-slate-800 dark:text-slate-100">
                            {{ $employee->name }}
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-xs text-slate-600 dark:text-slate-300">
                            {{ $employee->position ?? '—' }}
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-xs text-slate-600 dark:text-slate-300">
                            {{ $employee->branch?->name ?? __('N/A') }}
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-xs text-right tabular-nums text-slate-700 dark:text-slate-200">
                            {{ number_format((float) $employee->salary, 2) }}
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-xs text-center">
                            @if ($employee->is_active)
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 mr-1"></span>
                                    {{ __('Active') }}
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">
                                    <span class="h-1.5 w-1.5 rounded-full bg-slate-400 mr-1"></span>
                                    {{ __('Inactive') }}
                                </span>
                            @endif
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-xs text-slate-600 dark:text-slate-300">
                            @if ($employee->user)
                                {{ $employee->user->name ?? $employee->user->email }}
                            @else
                                <span class="text-slate-400">{{ __('Not linked') }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-xs text-right">
                            <a href="{{ route('app.hrm.employees.edit', $employee->id) }}"
                               class="inline-flex items-center rounded-lg border border-slate-200 dark:border-slate-700 px-2 py-1 text-[11px] font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">
                                <i class="mdi mdi-pencil-outline text-[13px] mr-1"></i>
                                {{ __('Edit') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-3 py-4 text-center text-xs text-slate-500 dark:text-slate-400">
                            {{ __('No employees found.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $employees->links() }}
    </div>
</div>
