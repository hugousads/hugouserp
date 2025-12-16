<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">{{ __('Module Fields') }}: {{ $module->localized_name }}</h1>
            <p class="text-gray-600 mt-1">{{ __('Manage custom fields for products in this module') }}</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('admin.modules.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition">
                {{ __('Back to Modules') }}
            </a>
            <button wire:click="openAddModal" class="px-4 py-2 bg-emerald-600 text-white rounded-xl hover:bg-emerald-700 transition flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                {{ __('Add Field') }}
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 bg-emerald-100 border border-emerald-300 text-emerald-700 rounded-xl">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ __('Order') }}</th>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ __('Field Key') }}</th>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ __('Label') }}</th>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ __('Type') }}</th>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ __('Required') }}</th>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ __('Show in List') }}</th>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ __('Status') }}</th>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($fields as $field)
                        <tr class="{{ $field->is_active ? '' : 'bg-gray-50 opacity-60' }}">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $field->sort_order }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <code class="px-2 py-1 bg-gray-100 rounded text-sm">{{ $field->field_key }}</code>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $field->field_label }}</div>
                                @if($field->field_label_ar)
                                    <div class="text-sm text-gray-500">{{ $field->field_label_ar }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                    {{ $fieldTypes[$field->field_type] ?? $field->field_type }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($field->is_required)
                                    <span class="text-red-500">{{ __('Yes') }}</span>
                                @else
                                    <span class="text-gray-400">{{ __('No') }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($field->show_in_list)
                                    <span class="text-emerald-500">{{ __('Yes') }}</span>
                                @else
                                    <span class="text-gray-400">{{ __('No') }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <button wire:click="toggleActive({{ $field->id }})" class="text-sm">
                                    @if($field->is_active)
                                        <span class="px-2 py-1 rounded-full bg-emerald-100 text-emerald-800">{{ __('Active') }}</span>
                                    @else
                                        <span class="px-2 py-1 rounded-full bg-gray-100 text-gray-800">{{ __('Inactive') }}</span>
                                    @endif
                                </button>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <button wire:click="openEditModal({{ $field->id }})" class="text-blue-600 hover:text-blue-900 me-3">
                                    {{ __('Edit') }}
                                </button>
                                <button wire:click="delete({{ $field->id }})" wire:confirm="{{ __('Are you sure you want to delete this field?') }}" class="text-red-600 hover:text-red-900">
                                    {{ __('Delete') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                {{ __('No fields defined for this module yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($showModal)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-bold text-gray-800">
                        {{ $isEditing ? __('Edit Field') : __('Add New Field') }}
                    </h2>
                </div>
                
                <form wire:submit="save" class="p-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Field Key') }} *</label>
                            <input type="text" wire:model="field_key" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="e.g. wood_type" {{ $isEditing ? 'disabled' : '' }}>
                            <p class="text-xs text-gray-500 mt-1">{{ __('Lowercase letters and underscores only') }}</p>
                            @error('field_key') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Field Type') }} *</label>
                            <select wire:model.live="field_type" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                @foreach($fieldTypes as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Label (English)') }} *</label>
                            <input type="text" wire:model="field_label" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                            @error('field_label') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Label (Arabic)') }}</label>
                            <input type="text" wire:model="field_label_ar" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" dir="rtl">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Placeholder (English)') }}</label>
                            <input type="text" wire:model="placeholder" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Placeholder (Arabic)') }}</label>
                            <input type="text" wire:model="placeholder_ar" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" dir="rtl">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Field Group') }}</label>
                            <input type="text" wire:model="field_group" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="e.g. specifications">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Sort Order') }}</label>
                            <input type="number" wire:model="sort_order" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                    </div>

                    @if(in_array($field_type, ['select', 'multiselect', 'radio']))
                        <div class="border border-gray-200 rounded-xl p-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Options') }}</label>
                            
                            <div class="space-y-2 mb-4">
                                @foreach($field_options as $key => $value)
                                    <div class="flex items-center gap-2 bg-gray-50 p-2 rounded-lg">
                                        <code class="text-sm text-gray-600">{{ $key }}</code>
                                        <span class="text-gray-400">=</span>
                                        <span class="text-sm flex-1">{{ $value }}</span>
                                        <button type="button" wire:click="removeOption('{{ $key }}')" class="text-red-500 hover:text-red-700">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                            
                            <div class="flex gap-2">
                                <input type="text" wire:model="newOptionKey" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="{{ __('Key (e.g. oak)') }}">
                                <input type="text" wire:model="newOptionValue" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="{{ __('Label (e.g. Oak / بلوط)') }}">
                                <button type="button" wire:click="addOption" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                                    {{ __('Add') }}
                                </button>
                            </div>
                        </div>
                    @endif
                    
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
                    
                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                        <button type="button" wire:click="closeModal" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition">
                            {{ __('Cancel') }}
                        </button>
                        <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-xl hover:bg-emerald-700 transition">
                            {{ $isEditing ? __('Update') : __('Create') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
