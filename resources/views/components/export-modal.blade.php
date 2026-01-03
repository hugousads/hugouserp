@props(['formats' => ['xlsx' => 'Excel', 'csv' => 'CSV', 'pdf' => 'PDF']])

<div class="z-modal fixed inset-0 overflow-y-auto" aria-labelledby="export-modal" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closeExportModal"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-2xl text-start overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ __('Export Data') }}
                    </h3>
                    <button wire:click="closeExportModal" class="text-gray-400 hover:text-gray-500">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="px-6 py-4 space-y-4 max-h-[70vh] overflow-y-auto">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Format') }}</label>
                        <select wire:model="exportFormat" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-emerald-500">
                            @foreach($formats as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Date Format') }}</label>
                        <select wire:model="exportDateFormat" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-emerald-500">
                            <option value="Y-m-d">2024-12-31</option>
                            <option value="d/m/Y">31/12/2024</option>
                            <option value="m/d/Y">12/31/2024</option>
                        </select>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="exportIncludeHeaders" class="w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500">
                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Include Headers') }}</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="exportRespectFilters" checked class="w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500">
                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Respect current filters') }}</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="exportIncludeTotals" class="w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500">
                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Include totals row') }}</span>
                    </label>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Max Rows') }}</label>
                    <select wire:model="exportMaxRows" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-emerald-500">
                        <option value="100">100</option>
                        <option value="500">500</option>
                        <option value="1000">1,000</option>
                        <option value="5000">5,000</option>
                        <option value="10000">10,000</option>
                        <option value="all">{{ __('All rows') }}</option>
                    </select>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Select Columns') }}</label>
                        <button type="button" wire:click="toggleAllExportColumns" class="text-xs text-emerald-600 hover:text-emerald-800">
                            {{ count($selectedExportColumns ?? []) === count($exportColumns ?? []) ? __('Deselect All') : __('Select All') }}
                        </button>
                    </div>
                    <div class="grid grid-cols-2 gap-2 max-h-60 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded-xl p-3 bg-gray-50 dark:bg-gray-700">
                        @foreach($exportColumns ?? [] as $key => $label)
                            <label class="flex items-center gap-2 cursor-pointer p-2 hover:bg-white dark:hover:bg-gray-600 rounded-lg transition">
                                <input type="checkbox" wire:model.live="selectedExportColumns" value="{{ $key }}" class="w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500">
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-1 text-xs text-gray-500">{{ count($selectedExportColumns ?? []) }} {{ __('of') }} {{ count($exportColumns ?? []) }} {{ __('columns selected') }}</p>
                </div>

                @if((int)($exportMaxRows ?? 0) > 5000 || ($exportMaxRows ?? '') === 'all')
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" wire:model="exportUseBackgroundJob" class="w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500 mt-0.5">
                        <div>
                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100 block">
                                {{ __('Process in background') }}
                            </span>
                            <span class="text-xs text-gray-600 dark:text-gray-400">
                                {{ __('Large exports will be queued and you\'ll receive a notification when ready') }}
                            </span>
                        </div>
                    </label>
                </div>
                @endif
            </div>

            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3">
                <button type="button" wire:click="closeExportModal" class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    {{ __('Cancel') }}
                </button>
                <button type="button" wire:click="export" class="px-4 py-2 bg-emerald-600 text-white rounded-xl hover:bg-emerald-700 transition flex items-center gap-2" wire:loading.attr="disabled">
                    <svg wire:loading wire:target="export" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <svg wire:loading.remove wire:target="export" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    <span>{{ __('Export') }}</span>
                </button>
            </div>
        </div>
    </div>
</div>
