<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">
                {{ $editMode ? __('Edit Work Center') : __('Create Work Center') }}
            </h1>
            <p class="text-sm text-slate-500">{{ __('Production station or machine configuration') }}</p>
        </div>
        <a href="{{ route('app.manufacturing.work-centers.index') }}" class="erp-btn erp-btn-secondary">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            {{ __('Back to List') }}
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200">
        <form wire:submit="save" class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Name --}}
                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700 mb-2">
                        {{ __('Name') }} <span class="text-red-500">*</span>
                    </label>
                    <input type="text" wire:model.live="name" id="name" class="erp-input @error('name') border-red-500 @enderror" placeholder="{{ __('e.g., Assembly Station 1') }}">
                    @error('name') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                </div>

                {{-- Code --}}
                <div>
                    <label for="code" class="block text-sm font-medium text-slate-700 mb-2">
                        {{ __('Code') }}
                        <span class="text-xs text-slate-400 font-normal">{{ __('(auto-generated)') }}</span>
                    </label>
                    <div class="flex items-center gap-2">
                        <input type="text" wire:model="code" id="code" 
                               class="erp-input flex-1 @error('code') border-red-500 @enderror {{ !$overrideCode && !$editMode ? 'bg-slate-50' : '' }}" 
                               placeholder="{{ __('e.g., WC-001') }}"
                               {{ !$overrideCode && !$editMode ? 'readonly' : '' }}>
                        @if(!$editMode)
                        <button type="button" wire:click="$toggle('overrideCode')" 
                                class="px-2 py-2 text-xs text-slate-500 hover:text-slate-700 border border-slate-300 rounded-lg"
                                title="{{ __('Edit code manually') }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                            </svg>
                        </button>
                        @endif
                    </div>
                    @error('code') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                </div>

                {{-- Name (Arabic) --}}
                <div>
                    <label for="name_ar" class="block text-sm font-medium text-slate-700 mb-2">
                        {{ __('Name (Arabic)') }}
                    </label>
                    <input type="text" wire:model="name_ar" id="name_ar" class="erp-input @error('name_ar') border-red-500 @enderror" placeholder="{{ __('Optional') }}" dir="rtl">
                    @error('name_ar') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                </div>

                {{-- Type --}}
                <div>
                    <label for="type" class="block text-sm font-medium text-slate-700 mb-2">
                        {{ __('Type') }} <span class="text-red-500">*</span>
                    </label>
                    <select wire:model="type" id="type" class="erp-input @error('type') border-red-500 @enderror">
                        <option value="manual">{{ __('Manual') }}</option>
                        <option value="machine">{{ __('Machine') }}</option>
                        <option value="assembly">{{ __('Assembly') }}</option>
                        <option value="quality_control">{{ __('Quality Control') }}</option>
                        <option value="packaging">{{ __('Packaging') }}</option>
                    </select>
                    @error('type') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                </div>

                {{-- Capacity Per Hour --}}
                <div>
                    <label for="capacity_per_hour" class="block text-sm font-medium text-slate-700 mb-2">
                        {{ __('Capacity (units/hour)') }}
                    </label>
                    <input type="number" step="0.01" min="0" wire:model="capacity_per_hour" id="capacity_per_hour" class="erp-input @error('capacity_per_hour') border-red-500 @enderror">
                    @error('capacity_per_hour') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                    <p class="text-xs text-slate-500 mt-1">{{ __('Optional: Production capacity') }}</p>
                </div>

                {{-- Cost Per Hour --}}
                <div>
                    <label for="cost_per_hour" class="block text-sm font-medium text-slate-700 mb-2">
                        {{ __('Cost Per Hour') }} <span class="text-red-500">*</span>
                    </label>
                    <input type="number" step="0.01" min="0" wire:model="cost_per_hour" id="cost_per_hour" class="erp-input @error('cost_per_hour') border-red-500 @enderror">
                    @error('cost_per_hour') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                    <p class="text-xs text-slate-500 mt-1">{{ __('Operating cost per hour') }}</p>
                </div>

                {{-- Status --}}
                <div>
                    <label for="status" class="block text-sm font-medium text-slate-700 mb-2">
                        {{ __('Status') }} <span class="text-red-500">*</span>
                    </label>
                    <select wire:model="status" id="status" class="erp-input @error('status') border-red-500 @enderror">
                        <option value="active">{{ __('Active') }}</option>
                        <option value="maintenance">{{ __('Maintenance') }}</option>
                        <option value="inactive">{{ __('Inactive') }}</option>
                    </select>
                    @error('status') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                </div>

                {{-- Description --}}
                <div class="md:col-span-2">
                    <label for="description" class="block text-sm font-medium text-slate-700 mb-2">
                        {{ __('Description') }}
                    </label>
                    <textarea wire:model="description" id="description" rows="3" class="erp-input @error('description') border-red-500 @enderror" placeholder="{{ __('Additional notes or specifications') }}"></textarea>
                    @error('description') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                </div>
            </div>

            {{-- Form Actions --}}
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200">
                <a href="{{ route('app.manufacturing.work-centers.index') }}" class="erp-btn erp-btn-secondary">
                    {{ __('Cancel') }}
                </a>
                <button type="submit" class="erp-btn erp-btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove>
                        {{ $editMode ? __('Update Work Center') : __('Create Work Center') }}
                    </span>
                    <span wire:loading>
                        {{ __('Saving...') }}
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>
