@props([
    'target' => null,
    'text' => null,
    'fullscreen' => false,
    'size' => 'md',
])

@php
$sizeClasses = match($size) {
    'sm' => 'w-4 h-4',
    'lg' => 'w-8 h-8',
    default => 'w-6 h-6',
};
@endphp

@if($fullscreen)
<div 
    wire:loading{{ $target ? ".delay.flex" : ".flex" }}
    @if($target) wire:target="{{ $target }}" @endif
    class="fixed inset-0 bg-white/80 dark:bg-slate-900/80 backdrop-blur-sm z-50 items-center justify-center"
    x-data="{ show: false }"
    x-show="show"
    x-on:livewire:navigate-start="show = true"
    x-on:livewire:navigate-end="show = false"
    x-on:operation-failed.window="show = false"
    style="display: none;"
>
    <div class="flex flex-col items-center gap-3">
        <svg class="{{ $sizeClasses }} animate-spin text-emerald-600" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
        </svg>
        @if($text)
        <span class="text-sm text-slate-600 dark:text-slate-300">{{ $text }}</span>
        @endif
    </div>
</div>
@else
<span 
    wire:loading{{ $target ? ".inline-flex" : ".inline-flex" }}
    @if($target) wire:target="{{ $target }}" @endif
    class="items-center gap-2 text-sm text-slate-500 dark:text-slate-400"
>
    <svg class="{{ $sizeClasses }} animate-spin text-emerald-600" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
    </svg>
    @if($text)
    <span>{{ $text }}</span>
    @endif
</span>
@endif
