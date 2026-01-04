<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">{{ __('Module Management') }}</h1>
            <p class="text-sm text-slate-500">{{ __('Manage system modules and their settings') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.modules.product-fields') }}" class="erp-btn-secondary">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                {{ __('Product Fields') }}
            </a>
            <a href="{{ route('admin.modules.create') }}" class="erp-btn erp-btn-primary">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('Add Module') }}
            </a>
        </div>
    </div>

    <div class="erp-card p-4">
        <div class="mb-6">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search modules...') }}" class="erp-input max-w-md">
        </div>

        @if(session()->has('success'))
            <div class="mb-4 p-3 bg-emerald-50 text-emerald-700 rounded-lg">{{ session('success') }}</div>
        @endif
        @if(session()->has('error'))
            <div class="mb-4 p-3 bg-red-50 text-red-700 rounded-lg">{{ session('error') }}</div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($modules as $module)
                <div class="border border-slate-200 rounded-xl p-4 hover:shadow-md transition-shadow {{ !$module->is_active ? 'opacity-60' : '' }}">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">{{ $module->icon ?? '📦' }}</span>
                            <div>
                                <h3 class="font-semibold text-slate-800">{{ $module->localized_name }}</h3>
                                <p class="text-xs text-slate-500">{{ $module->key }}</p>
                            </div>
                        </div>
                        @if($module->is_core)
                            <span class="px-2 py-0.5 text-xs bg-amber-100 text-amber-700 rounded-full">{{ __('Core') }}</span>
                        @endif
                    </div>
                    
                    <p class="text-sm text-slate-600 mb-3 line-clamp-2">{{ $module->localized_description ?? __('No description') }}</p>
                    
                    {{-- Module Type Badge --}}
                    <div class="flex items-center gap-2 mb-3">
                        <span class="px-2 py-0.5 text-xs rounded-full {{ $module->getModuleTypeColor() }}">
                            {{ $module->getModuleTypeLabel() }}
                        </span>
                        @if($module->supports_items)
                            <span class="px-2 py-0.5 text-xs bg-green-100 text-green-700 rounded-full">
                                {{ __('Creates Items') }}
                            </span>
                        @endif
                    </div>
                    
                    <div class="flex items-center justify-between pt-3 border-t">
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-slate-500">{{ $module->branches_count }} {{ __('branches') }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button wire:click="toggleActive({{ $module->id }})" class="p-1.5 rounded-lg {{ $module->is_active ? 'text-emerald-600 hover:bg-emerald-50' : 'text-slate-400 hover:bg-slate-100' }}" title="{{ $module->is_active ? __('Deactivate') : __('Activate') }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    @if($module->is_active)
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    @else
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    @endif
                                </svg>
                            </button>
                            <a href="{{ route('admin.modules.edit', $module) }}" class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center py-8 text-slate-500">{{ __('No modules found') }}</div>
            @endforelse
        </div>

        <div class="mt-4">
            {{ $modules->links() }}
        </div>
    </div>
</div>
