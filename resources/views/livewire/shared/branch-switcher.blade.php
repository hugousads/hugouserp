{{-- Branch Switcher Component - Enhanced UX --}}
@if($canSwitch && count($branches) > 0)
<div class="px-3 py-3 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
    {{-- Header with Role indicator --}}
    <div class="flex items-center justify-between mb-2">
        <span class="text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">
            {{ __('Branch Context') }}
        </span>
        @if(!$selectedBranchId)
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">
                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                {{ __('Super Admin') }}
            </span>
        @endif
    </div>
    
    {{-- Branch Selector --}}
    <div class="relative" x-data="{ open: false }">
        <button 
            @click="open = !open"
            type="button"
            class="w-full flex items-center justify-between px-3 py-2 text-sm rounded-lg border transition-all
                   {{ $selectedBranchId 
                      ? 'border-emerald-300 dark:border-emerald-600 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-300' 
                      : 'border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200' }}
                   hover:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50"
        >
            <span class="flex items-center gap-2">
                @if($selectedBranch)
                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    <span class="font-medium">{{ $selectedBranch->name }}</span>
                    @if($selectedBranch->code)
                        <span class="text-xs opacity-60">({{ $selectedBranch->code }})</span>
                    @endif
                @else
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="font-medium">{{ __('All Branches') }}</span>
                @endif
            </span>
            <svg class="w-4 h-4 text-slate-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        
        {{-- Dropdown --}}
        <div 
            x-show="open" 
            @click.away="open = false"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="transform opacity-0 scale-95"
            x-transition:enter-end="transform opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="transform opacity-100 scale-100"
            x-transition:leave-end="transform opacity-0 scale-95"
            class="absolute z-50 mt-1 w-full bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 max-h-64 overflow-auto"
            style="display: none;"
        >
            {{-- All Branches Option --}}
            <button 
                wire:click="switchBranch(null)"
                @click="open = false"
                class="w-full flex items-center gap-3 px-3 py-2.5 text-sm text-start hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors
                       {{ !$selectedBranchId ? 'bg-purple-50 dark:bg-purple-900/20' : '' }}"
            >
                <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <p class="font-medium text-slate-700 dark:text-slate-200">{{ __('All Branches') }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Full admin view') }}</p>
                </div>
                @if(!$selectedBranchId)
                    <svg class="w-5 h-5 text-purple-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                @endif
            </button>
            
            <div class="border-t border-slate-100 dark:border-slate-700"></div>
            
            {{-- Branch Options --}}
            @foreach($branches as $branch)
                <button 
                    wire:click="switchBranch({{ $branch['id'] }})"
                    @click="open = false"
                    class="w-full flex items-center gap-3 px-3 py-2.5 text-sm text-start hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors
                           {{ $selectedBranchId == $branch['id'] ? 'bg-emerald-50 dark:bg-emerald-900/20' : '' }}"
                >
                    <div class="w-8 h-8 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
                        <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400">
                            {{ strtoupper(substr($branch['name'], 0, 2)) }}
                        </span>
                    </div>
                    <div class="flex-1">
                        <p class="font-medium text-slate-700 dark:text-slate-200">{{ $branch['name'] }}</p>
                        @if($branch['code'])
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Code') }}: {{ $branch['code'] }}</p>
                        @endif
                    </div>
                    @if($selectedBranchId == $branch['id'])
                        <svg class="w-5 h-5 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                    @endif
                </button>
            @endforeach
        </div>
    </div>
    
    {{-- Context Info --}}
    @if($selectedBranch)
        <div class="mt-2 p-2 rounded-lg bg-emerald-100/50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800">
            <div class="flex items-start gap-2">
                <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                <div class="flex-1 min-w-0">
                    <p class="text-xs text-emerald-700 dark:text-emerald-300">
                        {{ __('Viewing the system from this branch perspective. Data and menus are filtered accordingly.') }}
                    </p>
                    <button 
                        wire:click="switchBranch(null)"
                        class="mt-1 text-xs font-medium text-emerald-600 dark:text-emerald-400 hover:underline"
                    >
                        {{ __('Exit Branch View') }} →
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

<script>
    document.addEventListener('livewire:init', () => {
        Livewire.on('branch-switched', () => {
            // Reload page to apply branch context changes
            window.location.reload();
        });
    });
</script>
@endif
