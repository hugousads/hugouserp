<div class="space-y-6">
    {{-- Global Loading Overlay --}}
    <div wire:loading.delay class="fixed inset-0 bg-slate-900/20 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-xl p-6 flex items-center gap-3">
            <svg class="animate-spin h-6 w-6 text-emerald-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-slate-700 font-medium">{{ __('Loading...') }}</span>
        </div>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">{{ __('Fixed Assets') }}</h1>
            <p class="text-sm text-slate-500">{{ __('Manage fixed assets and depreciation') }}</p>
        </div>
        <div class="flex items-center gap-2">
            @can('fixed-assets.create')
            <a href="{{ route('app.fixed-assets.create') }}" class="erp-btn erp-btn-primary">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('New Asset') }}
            </a>
            @endcan
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-4 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm">{{ __('Total Assets') }}</p>
                    <p class="text-2xl font-bold">{{ number_format($statistics['total_assets']) }}</p>
                </div>
                <div class="bg-white/20 rounded-lg p-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
            </div>
        </div>
        <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-xl p-4 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-emerald-100 text-sm">{{ __('Active Assets') }}</p>
                    <p class="text-2xl font-bold">{{ number_format($statistics['active_assets']) }}</p>
                </div>
                <div class="bg-white/20 rounded-lg p-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>
        <div class="bg-gradient-to-br from-amber-500 to-amber-600 rounded-xl p-4 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-amber-100 text-sm">{{ __('Total Value') }}</p>
                    <p class="text-2xl font-bold">{{ number_format($statistics['total_value'], 2) }}</p>
                </div>
                <div class="bg-white/20 rounded-lg p-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-4 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-sm">{{ __('Book Value') }}</p>
                    <p class="text-2xl font-bold">{{ number_format($statistics['total_book_value'], 2) }}</p>
                </div>
                <div class="bg-white/20 rounded-lg p-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                </div>
            </div>
        </div>
    </div>

    <div class="erp-card p-4">
        <div class="flex flex-col lg:flex-row gap-4 mb-6">
            <div class="flex-1 relative">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search by asset code, name or serial...') }}" class="erp-input pr-10">
                <div wire:loading.delay wire:target="search" class="absolute right-3 top-1/2 -translate-y-1/2">
                    <svg class="animate-spin h-4 w-4 text-emerald-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            </div>
            <div class="relative">
                <select wire:model.live="status" class="erp-input w-full lg:w-40">
                    <option value="">{{ __('All Status') }}</option>
                    <option value="active">{{ __('Active') }}</option>
                    <option value="disposed">{{ __('Disposed') }}</option>
                    <option value="sold">{{ __('Sold') }}</option>
                    <option value="retired">{{ __('Retired') }}</option>
                </select>
            </div>
            <div class="relative">
                <select wire:model.live="category" class="erp-input w-full lg:w-48">
                    <option value="">{{ __('All Categories') }}</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}">{{ $cat }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="erp-table">
                <thead>
                    <tr>
                        <th wire:click="sortBy('asset_code')" class="cursor-pointer hover:bg-slate-100">
                            <div class="flex items-center gap-2">
                                {{ __('Asset Code') }}
                                @if($sortField === 'asset_code')
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDirection === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/>
                                    </svg>
                                @endif
                            </div>
                        </th>
                        <th wire:click="sortBy('name')" class="cursor-pointer hover:bg-slate-100">
                            <div class="flex items-center gap-2">
                                {{ __('Asset Name') }}
                                @if($sortField === 'name')
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDirection === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/>
                                    </svg>
                                @endif
                            </div>
                        </th>
                        <th>{{ __('Asset Category') }}</th>
                        <th>{{ __('Purchase Cost') }}</th>
                        <th>{{ __('Book Value') }}</th>
                        <th>{{ __('Depreciation Method') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-center">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assets as $asset)
                        <tr wire:key="asset-{{ $asset->id }}">
                            <td class="font-medium text-slate-700">
                                {{ $asset->asset_code }}
                            </td>
                            <td>
                                <div class="font-medium text-slate-800">{{ $asset->name }}</div>
                                @if($asset->location)
                                    <div class="text-xs text-slate-500">📍 {{ $asset->location }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-slate-100 text-slate-700">
                                    {{ $asset->category }}
                                </span>
                            </td>
                            <td>{{ number_format($asset->purchase_cost, 2) }}</td>
                            <td class="font-medium text-emerald-600">{{ number_format($asset->book_value, 2) }}</td>
                            <td>
                                @if($asset->depreciation_method === 'straight_line')
                                    {{ __('Straight Line') }}
                                @elseif($asset->depreciation_method === 'declining_balance')
                                    {{ __('Declining Balance') }}
                                @else
                                    {{ __('Units of Production') }}
                                @endif
                            </td>
                            <td>
                                @if($asset->status === 'active')
                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-emerald-100 text-emerald-700">{{ __('Active') }}</span>
                                @elseif($asset->status === 'disposed')
                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-slate-100 text-slate-700">{{ __('Disposed') }}</span>
                                @elseif($asset->status === 'sold')
                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-700">{{ __('Sold') }}</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-amber-100 text-amber-700">{{ __('Retired') }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex items-center justify-center gap-2">
                                    @can('fixed-assets.edit')
                                    <a href="{{ route('app.fixed-assets.edit', $asset) }}" class="text-blue-600 hover:text-blue-800" title="{{ __('Edit') }}">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-12">
                                <div class="text-slate-400">
                                    <svg class="w-16 h-16 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                    <p class="text-lg font-medium">{{ __('No assets found') }}</p>
                                    <p class="text-sm">{{ __('Start by creating your first fixed asset') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($assets->hasPages())
            <div class="mt-4">
                {{ $assets->links() }}
            </div>
        @endif
    </div>
</div>
