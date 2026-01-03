{{-- Branch Switcher Component --}}
@if($canSwitch && count($branches) > 0)
<div class="px-3 py-2 border-b border-slate-200 dark:border-slate-700">
    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">
        {{ __('View as Branch') }}
    </label>
    <div class="relative">
        <select 
            wire:model.live="selectedBranchId"
            wire:change="switchBranch($event.target.value)"
            class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 
                   focus:border-emerald-500 focus:ring-emerald-500 pr-8 py-1.5"
        >
            <option value="">{{ __('All Branches (Admin View)') }}</option>
            @foreach($branches as $branch)
                <option value="{{ $branch['id'] }}">
                    {{ $branch['name'] }} {{ $branch['code'] ? '(' . $branch['code'] . ')' : '' }}
                </option>
            @endforeach
        </select>
        <div class="absolute inset-y-0 end-0 flex items-center pe-2 pointer-events-none">
            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path>
            </svg>
        </div>
    </div>
    @if($selectedBranch)
        <p class="mt-1 text-xs text-emerald-600 dark:text-emerald-400">
            <svg class="inline w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            {{ __('Viewing :branch context', ['branch' => $selectedBranch->name]) }}
        </p>
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
