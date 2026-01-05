{{-- resources/views/livewire/command-palette.blade.php --}}
<div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl overflow-hidden">
    {{-- Search Input --}}
    <div class="relative">
        <svg class="absolute left-4 top-4 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
        </svg>
        <input 
            type="text" 
            wire:model.live.debounce.300ms="query"
            placeholder="{{ __('Search products, customers, suppliers, invoices...') }}"
            class="w-full pl-12 pr-4 py-4 bg-transparent border-0 border-b border-slate-200 dark:border-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:ring-0 focus:outline-none"
            autofocus
            @keydown.down.prevent="$wire.moveDown()"
            @keydown.up.prevent="$wire.moveUp()"
            @keydown.enter.prevent="$wire.selectResult({{ $selectedIndex }})"
        />
    </div>

    {{-- Results --}}
    @if(!empty($results))
    <div class="max-h-96 overflow-y-auto">
        @foreach($results as $index => $result)
        <a href="{{ $result['url'] }}" 
           wire:key="result-{{ $index }}"
           wire:navigate
           class="flex items-center gap-4 px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition {{ $index === $selectedIndex ? 'bg-slate-50 dark:bg-slate-700/50' : '' }}">
            <div class="flex-shrink-0 text-2xl">
                {{ $result['icon'] }}
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-medium text-slate-900 dark:text-slate-100 truncate">
                    {{ $result['name'] }}
                </div>
                @if(!empty($result['subtitle']))
                <div class="text-xs text-slate-500 dark:text-slate-400 truncate">
                    {{ $result['subtitle'] }}
                </div>
                @endif
            </div>
            <div class="flex-shrink-0">
                <span class="text-xs px-2 py-1 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-400 rounded">
                    {{ $result['type'] }}
                </span>
            </div>
        </a>
        @endforeach
    </div>
    @elseif(strlen($query) >= 2)
    <div class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">
        <svg class="mx-auto h-12 w-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <p>{{ __('No results found') }}</p>
    </div>
    @else
    <div class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">
        <svg class="mx-auto h-12 w-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
        </svg>
        <p>{{ __('Type to search...') }}</p>
    </div>
    @endif

    {{-- Footer Hints --}}
    <div class="border-t border-slate-200 dark:border-slate-700 px-4 py-2 flex items-center justify-between bg-slate-50 dark:bg-slate-900/50">
        <div class="flex items-center gap-4 text-xs text-slate-500 dark:text-slate-400">
            <span class="flex items-center gap-1">
                <kbd class="px-1.5 py-0.5 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded text-xs">↑</kbd>
                <kbd class="px-1.5 py-0.5 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded text-xs">↓</kbd>
                {{ __('to navigate') }}
            </span>
            <span class="flex items-center gap-1">
                <kbd class="px-1.5 py-0.5 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded text-xs">↵</kbd>
                {{ __('to select') }}
            </span>
        </div>
        <span class="text-xs text-slate-400">
            <kbd class="px-1.5 py-0.5 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded text-xs">ESC</kbd>
            {{ __('to close') }}
        </span>
    </div>
</div>
