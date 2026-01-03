<div class="space-y-6" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h1 class="text-lg md:text-xl font-semibold text-slate-800">
                {{ __('Scheduled Reports') }}
            </h1>
            <p class="text-sm text-slate-500">
                {{ __('Set up automatic report delivery to your email.') }}
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
        <div class="xl:col-span-2 space-y-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-800 mb-3">
                    {{ __('Your Scheduled Reports') }}
                </h2>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-xs md:text-sm">
                        <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-slate-500">#</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-slate-500">{{ __('Report') }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-slate-500">{{ __('Schedule') }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-slate-500">{{ __('Recipient') }}</th>
                            <th class="px-3 py-2 text-right text-[11px] font-medium text-slate-500">{{ __('Actions') }}</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                        @forelse($reports as $report)
                            @php
                                $tpl = $report->template;
                            @endphp
                            <tr>
                                <td class="px-3 py-1.5">{{ $report->id }}</td>
                                <td class="px-3 py-1.5">
                                    @if($tpl)
                                        <div class="flex flex-col">
                                            <span class="font-medium text-[11px] text-slate-800">{{ $tpl->name }}</span>
                                            <span class="text-[10px] text-slate-400">{{ strtoupper($tpl->output_type) }}</span>
                                        </div>
                                    @else
                                        <span class="text-[11px] text-slate-400">{{ __('Custom') }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-1.5 text-[11px] text-slate-700">
                                    {{ \App\Livewire\Admin\Reports\ScheduledReportsManager::formatCronExpression($report->cron_expression) }}
                                </td>
                                <td class="px-3 py-1.5 text-[11px] text-slate-700">
                                    {{ $report->recipient_email ?? $report->user?->email }}
                                </td>
                                <td class="px-3 py-1.5 text-right">
                                    <button type="button" wire:click="edit({{ $report->id }})"
                                            class="text-[11px] text-indigo-600 hover:text-indigo-700 mr-2">
                                        {{ __('Edit') }}
                                    </button>
                                    <button type="button" wire:click="delete({{ $report->id }})"
                                            class="text-[11px] text-red-500 hover:text-red-600">
                                        {{ __('Delete') }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-3 text-center text-xs text-slate-500">
                                    {{ __('No scheduled reports yet. Create your first one!') }}
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $reports->links() }}
                </div>
            </div>
        </div>

        <div class="space-y-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-800 mb-3">
                    {{ $editingId ? __('Edit Schedule') : __('New Schedule') }}
                </h2>

                <div class="space-y-3 text-xs md:text-sm">
                    {{-- Report Template Selection --}}
                    <div>
                        <label class="block text-[11px] font-medium text-slate-500 mb-0.5">
                            {{ __('Report') }} <span class="text-red-500">*</span>
                        </label>
                        <select wire:model="templateId" wire:change="applyTemplate"
                                class="w-full rounded border border-slate-200 bg-white px-2 py-1 text-xs">
                            <option value="">{{ __('Select a report...') }}</option>
                            @foreach($templates as $tpl)
                                <option value="{{ $tpl['id'] }}">
                                    {{ $tpl['name'] }} ({{ strtoupper($tpl['output_type']) }})
                                </option>
                            @endforeach
                        </select>
                        @error('templateId')
                        <p class="mt-0.5 text-[11px] text-red-500">{{ $message }}</p>
                        @enderror
                        <p class="mt-0.5 text-[10px] text-slate-400">
                            {{ __('Choose which report to schedule') }}
                        </p>
                    </div>

                    {{-- Frequency Selection --}}
                    <div>
                        <label class="block text-[11px] font-medium text-slate-500 mb-0.5">
                            {{ __('Frequency') }}
                        </label>
                        <select wire:model.live="frequency"
                                class="w-full rounded border border-slate-200 bg-white px-2 py-1 text-xs">
                            <option value="daily">{{ __('Daily') }}</option>
                            <option value="weekly">{{ __('Weekly') }}</option>
                            <option value="monthly">{{ __('Monthly') }}</option>
                            <option value="quarterly">{{ __('Quarterly') }}</option>
                        </select>
                    </div>

                    {{-- Day Selection based on frequency --}}
                    @if($frequency === 'weekly')
                    <div>
                        <label class="block text-[11px] font-medium text-slate-500 mb-0.5">
                            {{ __('Day of Week') }}
                        </label>
                        <select wire:model.live="dayOfWeek"
                                class="w-full rounded border border-slate-200 bg-white px-2 py-1 text-xs">
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
                        <label class="block text-[11px] font-medium text-slate-500 mb-0.5">
                            {{ __('Day of Month') }}
                        </label>
                        <select wire:model.live="dayOfMonth"
                                class="w-full rounded border border-slate-200 bg-white px-2 py-1 text-xs">
                            @for($i = 1; $i <= 28; $i++)
                                <option value="{{ $i }}">{{ $i }}</option>
                            @endfor
                        </select>
                    </div>
                    @endif

                    {{-- Time Selection --}}
                    <div>
                        <label class="block text-[11px] font-medium text-slate-500 mb-0.5">
                            {{ __('Time') }}
                        </label>
                        <input type="time" wire:model.live="timeOfDay"
                               class="w-full rounded border border-slate-200 bg-white px-2 py-1 text-xs">
                    </div>

                    {{-- Recipient Email --}}
                    <div>
                        <label class="block text-[11px] font-medium text-slate-500 mb-0.5">
                            {{ __('Send To') }}
                        </label>
                        <input type="email" wire:model="recipientEmail"
                               placeholder="{{ __('your@email.com') }}"
                               class="w-full rounded border border-slate-200 bg-white px-2 py-1 text-xs">
                        @error('recipientEmail')
                        <p class="mt-0.5 text-[11px] text-red-500">{{ $message }}</p>
                        @enderror
                        <p class="mt-0.5 text-[10px] text-slate-400">
                            {{ __('Reports will be sent to this email') }}
                        </p>
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
                        <div>
                            <label class="block text-[11px] font-medium text-slate-500 mb-0.5">
                                {{ __('Cron Expression') }}
                            </label>
                            <input type="text" wire:model="cronExpression"
                                   class="w-full rounded border border-slate-200 bg-white px-2 py-1 text-xs font-mono">
                            @error('cronExpression')
                            <p class="mt-0.5 text-[11px] text-red-500">{{ $message }}</p>
                            @enderror
                            <p class="mt-0.5 text-[10px] text-slate-400">
                                {{ __('Auto-generated from schedule above') }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-[11px] font-medium text-slate-500 mb-0.5">
                                {{ __('Run As User') }}
                            </label>
                            <select wire:model="userId"
                                    class="w-full rounded border border-slate-200 bg-white px-2 py-1 text-xs">
                                <option value="">{{ __('Current user') }}</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-[11px] font-medium text-slate-500 mb-0.5">
                                {{ __('Custom Filters (JSON)') }}
                            </label>
                            <textarea wire:model="filtersJson" rows="3"
                                      placeholder='{"branch_id": 1}'
                                      class="w-full rounded border border-slate-200 bg-white px-2 py-1 text-xs font-mono"></textarea>
                            @error('filtersJson')
                            <p class="mt-0.5 text-[11px] text-red-500">{{ $message }}</p>
                            @enderror
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
                            {{ $editingId ? __('Save Changes') : __('Create Schedule') }}
                        </button>
                    </div>
                </div>
            </div>

            {{-- Helper Info --}}
            <div class="rounded-2xl border border-blue-100 bg-blue-50 p-3 text-[11px] text-blue-800">
                <p class="font-medium mb-1">{{ __('How it works') }}</p>
                <ul class="list-disc list-inside space-y-0.5 text-[10px]">
                    <li>{{ __('Select a report template') }}</li>
                    <li>{{ __('Choose how often to send it') }}</li>
                    <li>{{ __('Reports are emailed automatically') }}</li>
                </ul>
            </div>

            <div wire:offline.class="block" class="hidden rounded-2xl border border-amber-200 bg-amber-50 p-3 text-[11px] text-amber-800">
                {{ __('You appear to be offline.') }}
            </div>
        </div>
    </div>
</div>
