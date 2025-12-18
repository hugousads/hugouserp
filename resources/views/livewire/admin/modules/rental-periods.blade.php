<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">{{ __('Rental Periods') }}: {{ $module->localized_name }}</h1>
            <p class="text-gray-600 mt-1">{{ __('Configure rental period options for this module') }}</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('admin.modules.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition">
                {{ __('Back to Modules') }}
            </a>
            <button wire:click="openAddModal" class="px-4 py-2 bg-emerald-600 text-white rounded-xl hover:bg-emerald-700 transition flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                {{ __('Add Period') }}
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 bg-emerald-100 border border-emerald-300 text-emerald-700 rounded-xl">
            {{ session('success') }}
        </div>
    @endif

    @if(session('warning'))
        <div class="mb-4 p-4 bg-amber-100 border border-amber-300 text-amber-700 rounded-xl">
            {{ session('warning') }}
        </div>
    @endif

    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ __('Order') }}</th>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ __('Period Key') }}</th>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ __('Name') }}</th>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ __('Type') }}</th>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ __('Duration') }}</th>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ __('Price Multiplier') }}</th>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ __('Default') }}</th>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ __('Status') }}</th>
                        <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($periods as $period)
                        <tr class="{{ $period->is_active ? '' : 'bg-gray-50 opacity-60' }}">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $period->sort_order }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <code class="px-2 py-1 bg-gray-100 rounded text-sm">{{ $period->period_key }}</code>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $period->period_name }}</div>
                                @if($period->period_name_ar)
                                    <div class="text-sm text-gray-500">{{ $period->period_name_ar }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                    {{ $periodTypes[$period->period_type] ?? $period->period_type }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                {{ $period->duration_value }} {{ $durationUnits[$period->duration_unit] ?? $period->duration_unit }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono">
                                x{{ number_format($period->price_multiplier, 2) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($period->is_default)
                                    <span class="px-2 py-1 text-xs rounded-full bg-emerald-100 text-emerald-800">{{ __('Default') }}</span>
                                @else
                                    <button wire:click="setDefault({{ $period->id }})" class="text-sm text-gray-500 hover:text-emerald-600">
                                        {{ __('Set Default') }}
                                    </button>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <button wire:click="toggleActive({{ $period->id }})" class="text-sm">
                                    @if($period->is_active)
                                        <span class="px-2 py-1 rounded-full bg-emerald-100 text-emerald-800">{{ __('Active') }}</span>
                                    @else
                                        <span class="px-2 py-1 rounded-full bg-gray-100 text-gray-800">{{ __('Inactive') }}</span>
                                    @endif
                                </button>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <button wire:click="openEditModal({{ $period->id }})" class="text-blue-600 hover:text-blue-900 me-3">
                                    {{ __('Edit') }}
                                </button>
                                <button wire:click="delete({{ $period->id }})" wire:confirm="{{ __('Are you sure?') }}" class="text-red-600 hover:text-red-900">
                                    {{ __('Delete') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-gray-500">
                                {{ __('No rental periods defined for this module yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($showModal)
        <div class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center z-50 p-4 overflow-y-auto" wire:click.self="closeModal">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-lg mx-auto my-auto max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-purple-500 to-purple-600 text-white">
                    <h2 class="text-xl font-bold flex items-center gap-2">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ $isEditing ? __('Edit Period') : __('Add New Period') }}
                    </h2>
                </div>
                
                <form wire:submit.prevent="save" class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Period Key') }} *</label>
                            <input type="text" wire:model="period_key" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500" placeholder="e.g. monthly" {{ $isEditing ? 'disabled' : '' }}>
                            @error('period_key') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Period Type') }} *</label>
                            <select wire:model="period_type" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500">
                                @foreach($periodTypes as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Name (English)') }} *</label>
                            <input type="text" wire:model="period_name" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500">
                            @error('period_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Name (Arabic)') }}</label>
                            <input type="text" wire:model="period_name_ar" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500" dir="rtl">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Duration Value') }} *</label>
                            <input type="number" wire:model="duration_value" min="1" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Duration Unit') }} *</label>
                            <select wire:model="duration_unit" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500">
                                @foreach($durationUnits as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Price Multiplier') }} *</label>
                            <input type="number" wire:model="price_multiplier" step="0.01" min="0" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500">
                            <p class="text-xs text-gray-500 mt-1">{{ __('e.g. 30 for monthly = 30x daily price') }}</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Sort Order') }}</label>
                            <input type="number" wire:model="sort_order" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500">
                        </div>
                    </div>
                    
                    <div class="flex gap-6">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="is_default" class="w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500">
                            <span class="text-sm text-gray-700">{{ __('Default Period') }}</span>
                        </label>
                        
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="is_active" class="w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500">
                            <span class="text-sm text-gray-700">{{ __('Active') }}</span>
                        </label>
                    </div>
                    
                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button type="button" wire:click="closeModal" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-300 dark:hover:bg-gray-600 transition" wire:loading.attr="disabled">
                            {{ __('Cancel') }}
                        </button>
                        <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-xl hover:bg-emerald-700 transition" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="save">
                                {{ $isEditing ? __('Update Period') : __('Create Period') }}
                            </span>
                            <span wire:loading wire:target="save" class="flex items-center gap-2">
                                <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ __('Saving...') }}
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
