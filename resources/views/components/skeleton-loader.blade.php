@props([
    'type' => 'rows', // rows, cards, stats, form
    'count' => 5,
    'columns' => 4,
])

@php
$animationDelay = function($index) {
    return "animation-delay: " . ($index * 100) . "ms";
};
@endphp

<div {{ $attributes->merge(['class' => 'animate-pulse']) }}>
    @if($type === 'rows')
        {{-- Table Row Skeleton --}}
        <div class="divide-y divide-slate-200 dark:divide-slate-700">
            @for($i = 0; $i < $count; $i++)
                <div class="flex items-center gap-4 p-4" style="{{ $animationDelay($i) }}">
                    {{-- Checkbox placeholder --}}
                    <div class="w-4 h-4 bg-slate-200 dark:bg-slate-700 rounded"></div>
                    
                    {{-- Content columns --}}
                    @php $widths = ['w-2/4', 'w-3/4', 'w-4/5', 'w-5/6']; @endphp
                    @for($j = 0; $j < $columns; $j++)
                        <div class="flex-1 space-y-2">
                            <div class="h-4 bg-slate-200 dark:bg-slate-700 rounded {{ $widths[$j % 4] }}"></div>
                            @if($j === 0)
                                <div class="h-3 bg-slate-100 dark:bg-slate-800 rounded w-1/2"></div>
                            @endif
                        </div>
                    @endfor
                    
                    {{-- Actions placeholder --}}
                    <div class="flex gap-2">
                        <div class="w-8 h-8 bg-slate-200 dark:bg-slate-700 rounded-lg"></div>
                        <div class="w-8 h-8 bg-slate-200 dark:bg-slate-700 rounded-lg"></div>
                    </div>
                </div>
            @endfor
        </div>
    @elseif($type === 'cards')
        {{-- Card Grid Skeleton --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            @for($i = 0; $i < $count; $i++)
                <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-700" style="{{ $animationDelay($i) }}">
                    {{-- Header --}}
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 bg-slate-200 dark:bg-slate-700 rounded-full"></div>
                        <div class="flex-1 space-y-2">
                            <div class="h-4 bg-slate-200 dark:bg-slate-700 rounded w-3/4"></div>
                            <div class="h-3 bg-slate-100 dark:bg-slate-800 rounded w-1/2"></div>
                        </div>
                    </div>
                    
                    {{-- Content --}}
                    <div class="space-y-3">
                        <div class="h-3 bg-slate-100 dark:bg-slate-800 rounded"></div>
                        <div class="h-3 bg-slate-100 dark:bg-slate-800 rounded w-5/6"></div>
                    </div>
                    
                    {{-- Footer --}}
                    <div class="flex justify-between items-center mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                        <div class="h-4 bg-slate-200 dark:bg-slate-700 rounded w-1/3"></div>
                        <div class="h-6 bg-slate-200 dark:bg-slate-700 rounded-full w-16"></div>
                    </div>
                </div>
            @endfor
        </div>
    @elseif($type === 'stats')
        {{-- Stats Cards Skeleton --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @for($i = 0; $i < min($count, 4); $i++)
                <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-700" style="{{ $animationDelay($i) }}">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 bg-slate-200 dark:bg-slate-700 rounded-lg"></div>
                        <div class="w-8 h-4 bg-slate-200 dark:bg-slate-700 rounded"></div>
                    </div>
                    <div class="h-6 bg-slate-200 dark:bg-slate-700 rounded w-1/2 mb-2"></div>
                    <div class="h-3 bg-slate-100 dark:bg-slate-800 rounded w-3/4"></div>
                </div>
            @endfor
        </div>
    @elseif($type === 'form')
        {{-- Form Skeleton --}}
        <div class="space-y-6">
            @for($i = 0; $i < $count; $i++)
                <div style="{{ $animationDelay($i) }}">
                    <div class="h-4 bg-slate-200 dark:bg-slate-700 rounded w-1/4 mb-2"></div>
                    <div class="h-10 bg-slate-100 dark:bg-slate-800 rounded-lg"></div>
                </div>
            @endfor
            
            {{-- Submit button --}}
            <div class="flex justify-end gap-3 pt-4">
                <div class="h-10 w-24 bg-slate-200 dark:bg-slate-700 rounded-lg"></div>
                <div class="h-10 w-32 bg-emerald-200 dark:bg-emerald-900/50 rounded-lg"></div>
            </div>
        </div>
    @elseif($type === 'table-header')
        {{-- Table Header Skeleton --}}
        <div class="bg-slate-50 dark:bg-slate-900 rounded-t-xl px-4 py-3 border-b border-slate-200 dark:border-slate-700">
            <div class="flex items-center gap-4">
                @for($i = 0; $i < $columns; $i++)
                    <div class="flex-1 h-4 bg-slate-200 dark:bg-slate-700 rounded"></div>
                @endfor
            </div>
        </div>
    @else
        {{-- Default Skeleton --}}
        @php $defaultWidths = [85, 70, 95, 60, 80]; @endphp
        <div class="space-y-4">
            @for($i = 0; $i < $count; $i++)
                <div class="h-4 bg-slate-200 dark:bg-slate-700 rounded" style="width: {{ $defaultWidths[$i % 5] }}%; {{ $animationDelay($i) }}"></div>
            @endfor
        </div>
    @endif
</div>
