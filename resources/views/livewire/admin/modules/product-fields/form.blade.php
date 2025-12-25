{{-- resources/views/livewire/admin/modules/product-fields/form.blade.php --}}
<div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                {{ $fieldId ? __('Edit Product Field') : __('New Product Field') }}
            </h1>
            <p class="text-gray-600 mt-1">
                {{ __('Configure custom product field for module: :module', ['module' => $module?->localized_name ?? __('Unknown')]) }}
            </p>
        </div>
    </div>

    @if(session('success'))
        <div class="p-4 bg-emerald-100 border border-emerald-300 text-emerald-700 rounded-xl">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="p-4 bg-red-100 border border-red-300 text-red-700 rounded-xl">
            {{ session('error') }}
        </div>
    @endif

    <form wire:submit.prevent="save" class="space-y-6 max-w-3xl">
        <div class="bg-white rounded-2xl shadow-lg p-6 space-y-4">
            <h3 class="font-medium text-gray-800 border-b pb-2">{{ __('Basic Information') }}</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Field Key') }} *</label>
                    <input type="text" wire:model="field_key" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500" placeholder="e.g. wood_type" {{ $fieldId ? 'disabled' : '' }}>
                    <p class="text-xs text-gray-500 mt-1">{{ __('Lowercase letters, numbers and underscores only') }}</p>
                    @error('field_key') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Field Type') }} *</label>
                    <select wire:model.live="field_type" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500">
                        @foreach($fieldTypes as $key => $label)
                            <option value="{{ $key }}">{{ __($label) }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Label (English)') }} *</label>
                    <input type="text" wire:model="field_label" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500">
                    @error('field_label') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Label (Arabic)') }}</label>
                    <input type="text" wire:model="field_label_ar" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500" dir="rtl">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Placeholder (English)') }}</label>
                    <input type="text" wire:model="placeholder" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Placeholder (Arabic)') }}</label>
                    <input type="text" wire:model="placeholder_ar" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500" dir="rtl">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Field Group') }}</label>
                    <select wire:model="field_group" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500">
                        @foreach($fieldGroups as $key => $label)
                            <option value="{{ $key }}">{{ __($label) }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Sort Order') }}</label>
                    <input type="number" wire:model="sort_order" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500" min="0">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Default Value') }}</label>
                    <input type="text" wire:model="default_value" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Validation Rules') }}</label>
                    <input type="text" wire:model="validation_rules" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500" placeholder="e.g. required|max:255">
                </div>
            </div>
        </div>

        @if(in_array($field_type, ['select', 'multiselect', 'radio']))
        <div class="bg-white rounded-2xl shadow-lg p-6 space-y-4">
            <h3 class="font-medium text-gray-800 border-b pb-2">{{ __('Options') }}</h3>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Options (one per line)') }}</label>
                <textarea wire:model="optionsText" rows="5" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500" placeholder="Option 1&#10;Option 2&#10;Option 3"></textarea>
                <p class="text-xs text-gray-500 mt-1">{{ __('Enter each option on a new line') }}</p>
            </div>
        </div>
        @endif

        <div class="bg-white rounded-2xl shadow-lg p-6 space-y-4">
            <h3 class="font-medium text-gray-800 border-b pb-2">{{ __('Display Settings') }}</h3>
            
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model="is_required" class="w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500">
                    <span class="text-sm text-gray-700">{{ __('Required') }}</span>
                </label>
                
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model="is_searchable" class="w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500">
                    <span class="text-sm text-gray-700">{{ __('Searchable') }}</span>
                </label>
                
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model="is_filterable" class="w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500">
                    <span class="text-sm text-gray-700">{{ __('Filterable') }}</span>
                </label>
                
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model="show_in_list" class="w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500">
                    <span class="text-sm text-gray-700">{{ __('Show in List') }}</span>
                </label>
                
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model="show_in_form" class="w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500">
                    <span class="text-sm text-gray-700">{{ __('Show in Form') }}</span>
                </label>
                
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model="is_active" class="w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500">
                    <span class="text-sm text-gray-700">{{ __('Active') }}</span>
                </label>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.modules.product-fields', ['moduleId' => $moduleId]) }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition">
                {{ __('Cancel') }}
            </a>
            <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-xl hover:bg-emerald-700 transition">
                {{ $fieldId ? __('Update') : __('Create') }}
            </button>
        </div>
    </form>
</div>
