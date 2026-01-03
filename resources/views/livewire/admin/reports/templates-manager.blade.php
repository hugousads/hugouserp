<div class="space-y-6" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h1 class="text-lg md:text-xl font-semibold text-slate-800">
                {{ __('Report templates') }}
            </h1>
            <p class="text-sm text-slate-500">
                {{ __('Create reusable report templates with default settings.') }}
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
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-slate-500">{{ __('Name') }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-slate-500">{{ __('Report Type') }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-slate-500">{{ __('Format') }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-slate-500">{{ __('Status') }}</th>
                            <th class="px-3 py-2 text-right text-[11px] font-medium text-slate-500">{{ __('Actions') }}</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                        @forelse($templates as $tpl)
                            <tr>
                                <td class="px-3 py-1.5 text-[11px] text-slate-500">{{ $tpl->id }}</td>
                                <td class="px-3 py-1.5">
                                    <div class="flex flex-col">
                                        <span class="text-[11px] font-medium text-slate-800">{{ $tpl->name }}</span>
                                        @if($tpl->description)
                                            <span class="text-[10px] text-slate-400">{{ Str::limit($tpl->description, 40) }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-1.5 text-[11px] text-slate-700">
                                    @php
                                        $route = collect($availableRoutes)->firstWhere('name', $tpl->route_name);
                                    @endphp
                                    {{ $route['label'] ?? $tpl->route_name }}
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
                                <td colspan="6" class="px-3 py-3 text-center text-xs text-slate-500">
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
                            {{ __('Template Name') }} <span class="text-red-500">*</span>
                        </label>
                        <input type="text" wire:model.live="name"
                               placeholder="{{ __('e.g., Monthly Sales Report') }}"
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
                                  placeholder="{{ __('Brief description of this report template') }}"
                                  class="w-full rounded border border-slate-200 bg-white px-2 py-1 text-xs"></textarea>
                        @error('description')
                        <p class="mt-0.5 text-[11px] text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-slate-500 mb-0.5">
                            {{ __('Report Type') }} <span class="text-red-500">*</span>
                        </label>
                        <select wire:model="routeName"
                                class="w-full rounded border border-slate-200 bg-white px-2 py-1 text-xs">
                            <option value="">{{ __('Select a report type') }}</option>
                            @foreach($availableRoutes as $route)
                                <option value="{{ $route['name'] }}">
                                    {{ $route['label'] }}
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
                                {{ __('Output Format') }}
                            </label>
                            <select wire:model="outputType"
                                    class="w-full rounded border border-slate-200 bg-white px-2 py-1 text-xs">
                                <option value="web">{{ __('Web (View Online)') }}</option>
                                <option value="excel">{{ __('Excel (.xlsx)') }}</option>
                                <option value="pdf">{{ __('PDF Document') }}</option>
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

                    {{-- Advanced Settings Toggle --}}
                    <div class="border-t border-slate-100 pt-3">
                        <button type="button" wire:click="$toggle('showAdvanced')"
                                class="flex items-center gap-1 text-[11px] text-slate-500 hover:text-slate-700">
                            <svg class="w-3 h-3 transition-transform {{ $showAdvanced ? 'rotate-90' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                            {{ __('Advanced Settings') }}
                        </button>
                    </div>

                    @if($showAdvanced)
                    <div class="space-y-3 pl-2 border-l-2 border-slate-100">
                        {{-- Key Override --}}
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <input type="checkbox" wire:model.live="overrideKey" id="override-key"
                                       class="rounded border-slate-300 text-xs">
                                <label for="override-key" class="text-[11px] text-slate-500">
                                    {{ __('Custom key (auto-generated by default)') }}
                                </label>
                            </div>
                            @if($overrideKey || $editingId)
                            <input type="text" wire:model="key"
                                   class="w-full rounded border border-slate-200 bg-white px-2 py-1 text-xs font-mono"
                                   {{ !$overrideKey && !$editingId ? 'readonly' : '' }}>
                            @else
                            <p class="text-[10px] text-slate-400 font-mono">{{ $key ?: __('Will be generated from name') }}</p>
                            @endif
                            @error('key')
                            <p class="mt-0.5 text-[11px] text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-[11px] font-medium text-slate-500 mb-0.5">
                                {{ __('Default Filters (JSON)') }}
                            </label>
                            <textarea wire:model="defaultFiltersJson" rows="3"
                                      placeholder='{"from": "2025-01-01", "to": "2025-01-31"}'
                                      class="w-full rounded border border-slate-200 bg-white px-2 py-1 text-xs font-mono"></textarea>
                            @error('defaultFiltersJson')
                            <p class="mt-0.5 text-[11px] text-red-500">{{ $message }}</p>
                            @enderror
                            <p class="mt-0.5 text-[10px] text-slate-400">
                                {{ __('Optional: Pre-fill filter values') }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-[11px] font-medium text-slate-500 mb-0.5">
                                {{ __('Export Columns') }}
                            </label>
                            <input type="text" wire:model="exportColumnsText"
                                   placeholder="id, name, status, total"
                                   class="w-full rounded border border-slate-200 bg-white px-2 py-1 text-xs">
                            @error('exportColumnsText')
                            <p class="mt-0.5 text-[11px] text-red-500">{{ $message }}</p>
                            @enderror
                            <p class="mt-0.5 text-[10px] text-slate-400">
                                {{ __('Optional: Comma-separated column names') }}
                            </p>
                        </div>
                    </div>
                    @endif

                    <div class="flex items-center justify-between gap-2 pt-2">
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

            {{-- Helper Info --}}
            <div class="rounded-2xl border border-blue-100 bg-blue-50 p-3 text-[11px] text-blue-800">
                <p class="font-medium mb-1">{{ __('Tips') }}</p>
                <ul class="list-disc list-inside space-y-0.5 text-[10px]">
                    <li>{{ __('Choose a descriptive name to easily identify reports') }}</li>
                    <li>{{ __('PDF is best for printing, Excel for data analysis') }}</li>
                    <li>{{ __('Use Advanced Settings for custom configurations') }}</li>
                </ul>
            </div>
        </div>
    </div>
</div>
