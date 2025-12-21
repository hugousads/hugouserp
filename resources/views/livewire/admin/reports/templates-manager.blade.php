<div class="space-y-6" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h1 class="text-lg md:text-xl font-semibold text-slate-800">
                {{ __('Report templates') }}
            </h1>
            <p class="text-sm text-slate-500">
                {{ __('Manage reusable report templates, default filters, and export settings.') }}
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
        <div class="xl:col-span-2 space-y-3">
            <div class="flex items-center justify-between gap-2">
                <div class="flex-1">
                    <input type="text" wire:model.live.debounce.400ms="search"
                           placeholder="{{ __('Search templates...') }}"
                           class="w-full rounded-2xl border border-slate-200 bg-white px-3 py-1.5 text-xs md:text-sm">
                </div>
                <button type="button" wire:click="createNew"
                        class="inline-flex items-center rounded-2xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                    {{ __('New template') }}
                </button>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-xs md:text-sm">
                        <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-slate-500">#</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-slate-500">{{ __('Key') }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-slate-500">{{ __('Name') }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-slate-500">{{ __('Route') }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-slate-500">{{ __('Type') }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-slate-500">{{ __('Active') }}</th>
                            <th class="px-3 py-2 text-right text-[11px] font-medium text-slate-500">{{ __('Actions') }}</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                        @forelse($templates as $tpl)
                            <tr>
                                <td class="px-3 py-1.5 text-[11px] text-slate-500">{{ $tpl->id }}</td>
                                <td class="px-3 py-1.5 text-[11px] font-mono text-slate-800">{{ $tpl->key }}</td>
                                <td class="px-3 py-1.5 text-[11px] text-slate-800">{{ $tpl->name }}</td>
                                <td class="px-3 py-1.5 text-[11px] text-slate-700">
                                    <div class="flex flex-col">
                                        <span>{{ $tpl->route_name }}</span>
                                        @php
                                            $route = collect($availableRoutes)->firstWhere('name', $tpl->route_name);
                                        @endphp
                                        @if($route)
                                            <span class="text-[10px] text-slate-400">/{{ $route['uri'] }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-1.5 text-[11px] text-slate-700">
                                    {{ strtoupper($tpl->output_type) }}
                                </td>
                                <td class="px-3 py-1.5 text-[11px]">
                                    @if($tpl->is_active)
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-medium text-emerald-700">
                                            {{ __('Active') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-slate-50 px-2 py-0.5 text-[10px] font-medium text-slate-500">
                                            {{ __('Disabled') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-1.5 text-right">
                                    <button type="button" wire:click="edit({{ $tpl->id }})"
                                            class="text-[11px] text-indigo-600 hover:text-indigo-700 mr-2">
                                        {{ __('Edit') }}
                                    </button>
                                    <button type="button" wire:click="delete({{ $tpl->id }})"
                                            class="text-[11px] text-red-500 hover:text-red-600">
                                        {{ __('Delete') }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-3 text-center text-xs text-slate-500">
                                    {{ __('No templates found.') }}
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $templates->links() }}
                </div>
            </div>
        </div>

        <div class="space-y-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-800 mb-3">
                    {{ $editingId ? __('Edit template') : __('New template') }}
                </h2>

                <div class="space-y-3 text-xs md:text-sm">

                    <div>
                        <label class="block text-[11px] font-medium text-slate-500 mb-0.5">
                            {{ __('Key') }}
                        </label>
                        <input type="text" wire:model="key"
                               class="w-full rounded border border-slate-200 bg-white px-2 py-1 text-xs">
                        @error('key')
                        <p class="mt-0.5 text-[11px] text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-slate-500 mb-0.5">
                            {{ __('Name') }}
                        </label>
                        <input type="text" wire:model="name"
                               class="w-full rounded border border-slate-200 bg-white px-2 py-1 text-xs">
                        @error('name')
                        <p class="mt-0.5 text-[11px] text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-slate-500 mb-0.5">
                            {{ __('Description') }}
                        </label>
                        <textarea wire:model="description" rows="2"
                                  class="w-full rounded border border-slate-200 bg-white px-2 py-1 text-xs"></textarea>
                        @error('description')
                        <p class="mt-0.5 text-[11px] text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-slate-500 mb-0.5">
                            {{ __('Route') }}
                        </label>
                        <select wire:model="routeName"
                                class="w-full rounded border border-slate-200 bg-white px-2 py-1 text-xs">
                            <option value="">{{ __('Select report route') }}</option>
                            @foreach($availableRoutes as $route)
                                <option value="{{ $route['name'] }}">
                                    {{ $route['name'] }} (/{{ $route['uri'] }})
                                </option>
                            @endforeach
                        </select>
                        @error('routeName')
                        <p class="mt-0.5 text-[11px] text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-medium text-slate-500 mb-0.5">
                                {{ __('Output type') }}
                            </label>
                            <select wire:model="outputType"
                                    class="w-full rounded border border-slate-200 bg-white px-2 py-1 text-xs">
                                @foreach($outputTypes as $type)
                                    <option value="{{ $type }}">{{ strtoupper($type) }}</option>
                                @endforeach
                            </select>
                            @error('outputType')
                            <p class="mt-0.5 text-[11px] text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex items-center gap-2 mt-5 md:mt-6">
                            <input type="checkbox" wire:model="isActive" id="tpl-active"
                                   class="rounded border-slate-300">
                            <label for="tpl-active" class="text-[11px] text-slate-700">
                                {{ __('Active') }}
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-slate-500 mb-0.5">
                            {{ __('Default filters (JSON)') }}
                        </label>
                        <textarea wire:model="defaultFiltersJson" rows="4"
                                  class="w-full rounded border border-slate-200 bg-white px-2 py-1 text-xs font-mono"></textarea>
                        @error('defaultFiltersJson')
                        <p class="mt-0.5 text-[11px] text-red-500">{{ $message }}</p>
                        @enderror
                        <p class="mt-0.5 text-[10px] text-slate-400">
                            {{ __('Example: {"from":"2025-01-01","to":"2025-01-31"}') }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-slate-500 mb-0.5">
                            {{ __('Export columns (comma separated)') }}
                        </label>
                        <input type="text" wire:model="exportColumnsText"
                               placeholder="external_order_id,source,status,total"
                               class="w-full rounded border border-slate-200 bg-white px-2 py-1 text-xs">
                        @error('exportColumnsText')
                        <p class="mt-0.5 text-[11px] text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-between gap-2">
                        <button type="button" wire:click="createNew"
                                class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                            {{ __('Reset') }}
                        </button>
                        <button type="button" wire:click="save"
                                class="inline-flex items-center rounded-lg border border-indigo-500 bg-indigo-500 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-600">
                            {{ $editingId ? __('Save changes') : __('Create template') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
