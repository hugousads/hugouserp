<div class="max-w-2xl mx-auto p-6">
    <div class="mb-6">
        <a href="{{ route('admin.reports.scheduled') }}" class="inline-flex items-center text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
            <svg class="w-5 h-5 me-2 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            {{ __('Back to Scheduled Reports') }}
        </a>
    </div>

    {{-- Quick help card --}}
    <div class="mb-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-4">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div class="text-sm text-blue-800 dark:text-blue-200">
                <p class="font-medium mb-1">{{ __('How Scheduled Reports Work') }}</p>
                <p class="text-blue-700 dark:text-blue-300">{{ __('Create a schedule to automatically generate and email reports. Choose a template, set how often to run, and enter recipient emails. Reports will be sent as attachments.') }}</p>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                {{ $scheduleId ? __('Edit Schedule') : __('New Scheduled Report') }}
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ __('Fill in the details below to schedule automatic report delivery.') }}</p>
        </div>

        <form wire:submit="save" class="p-6 space-y-6">
            {{-- Basic Information Section --}}
            <div class="space-y-4">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wider">{{ __('Basic Information') }}</h3>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Schedule Name') }} *</label>
                    <input type="text" wire:model="scheduleName" 
                           placeholder="{{ __('e.g., Weekly Sales Summary') }}"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:ring-emerald-500 focus:border-emerald-500">
                    <p class="text-xs text-gray-500 mt-1">{{ __('Give your schedule a descriptive name so you can easily identify it later.') }}</p>
                    @error('scheduleName') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Report Template') }} *</label>
                    <select wire:model="templateId" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:ring-emerald-500 focus:border-emerald-500">
                        <option value="">{{ __('Select a template...') }}</option>
                        @foreach($templates as $template)
                            <option value="{{ $template->id }}">{{ $template->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">{{ __('Choose which report to generate. Templates define what data is included.') }}</p>
                    @error('templateId') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
            </div>

            {{-- Schedule Timing Section --}}
            <div class="space-y-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wider">{{ __('Schedule Timing') }}</h3>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Frequency') }} *</label>
                        <select wire:model.live="frequency" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="daily">{{ __('Daily') }}</option>
                            <option value="weekly">{{ __('Weekly') }}</option>
                            <option value="monthly">{{ __('Monthly') }}</option>
                            <option value="quarterly">{{ __('Quarterly') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Time') }} *</label>
                        <input type="time" wire:model="timeOfDay" 
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                </div>

            @if($frequency === 'weekly')
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Day of Week') }}</label>
                    <select wire:model="dayOfWeek" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white">
                        <option value="0">{{ __('Sunday') }}</option>
                        <option value="1">{{ __('Monday') }}</option>
                        <option value="2">{{ __('Tuesday') }}</option>
                        <option value="3">{{ __('Wednesday') }}</option>
                        <option value="4">{{ __('Thursday') }}</option>
                        <option value="5">{{ __('Friday') }}</option>
                        <option value="6">{{ __('Saturday') }}</option>
                    </select>
                </div>
            @endif

            @if(in_array($frequency, ['monthly', 'quarterly']))
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Day of Month') }}</label>
                    <select wire:model="dayOfMonth" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:ring-emerald-500 focus:border-emerald-500">
                        @for($i = 1; $i <= 28; $i++)
                            <option value="{{ $i }}">{{ $i }}</option>
                        @endfor
                    </select>
                    <p class="text-xs text-gray-500 mt-1">{{ __('We limit to 28 to ensure the report runs every month.') }}</p>
                </div>
            @endif
            </div>

            {{-- Delivery Settings Section --}}
            <div class="space-y-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wider">{{ __('Delivery Settings') }}</h3>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Recipient Emails') }} *</label>
                    <textarea wire:model="recipientEmails" rows="2"
                              placeholder="{{ __('email1@example.com, email2@example.com') }}"
                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:ring-emerald-500 focus:border-emerald-500"></textarea>
                    <p class="text-xs text-gray-500 mt-1">{{ __('Enter email addresses separated by commas. The report will be sent as an attachment.') }}</p>
                    @error('recipientEmails') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Output Format') }}</label>
                        <select wire:model="format" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="pdf">{{ __('PDF') }} - {{ __('Best for printing') }}</option>
                            <option value="excel">{{ __('Excel') }} - {{ __('Best for analysis') }}</option>
                            <option value="csv">{{ __('CSV') }} - {{ __('Universal format') }}</option>
                        </select>
                    </div>
                    <div class="flex items-center pt-6">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:model="isActive" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-emerald-300 dark:peer-focus:ring-emerald-800 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-500 peer-checked:bg-emerald-600"></div>
                            <span class="ms-3 text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Active') }}</span>
                        </label>
                    </div>
                </div>
            </div>

            {{-- Advanced Options (Collapsible) --}}
            <div x-data="{ showAdvanced: false }" class="pt-4 border-t border-gray-200 dark:border-gray-700">
                <button type="button" @click="showAdvanced = !showAdvanced" 
                        class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                    <svg class="w-4 h-4 transition-transform" :class="showAdvanced ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                    {{ __('Advanced Options') }}
                </button>
                
                <div x-show="showAdvanced" 
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 max-h-0"
                     x-transition:enter-end="opacity-100 max-h-96"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 max-h-96"
                     x-transition:leave-end="opacity-0 max-h-0"
                     class="mt-4 space-y-4 ps-6 border-s-2 border-gray-200 dark:border-gray-700 overflow-hidden">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Advanced options allow you to customize report filters. These are optional - leave empty to use template defaults.') }}
                    </p>
                    
                    {{-- Placeholder for future advanced filter options --}}
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                        <p class="text-sm text-gray-600 dark:text-gray-400 italic">
                            {{ __('Filter options depend on the selected template. Select a template to see available filters.') }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                <a href="{{ route('admin.reports.scheduled') }}" class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                    {{ __('Cancel') }}
                </a>
                <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700">
                    {{ $scheduleId ? __('Update') : __('Create') }}
                </button>
            </div>
        </form>
    </div>
</div>
