{{-- 
    Simple, clean modal component following the pattern from inventory categories page
    Usage:
    @if($showModal)
        <x-modal wire:click.self="closeModal">
            <div class="px-6 py-4 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white">
                <h3 class="text-lg font-semibold">Modal Title</h3>
            </div>
            <div class="p-6">
                Modal content...
            </div>
        </x-modal>
    @endif
--}}

@props([
    'maxWidth' => 'lg',
])

@php
$maxWidthClass = match($maxWidth) {
    'sm' => 'max-w-sm',
    'md' => 'max-w-md',
    'lg' => 'max-w-lg',
    'xl' => 'max-w-xl',
    '2xl' => 'max-w-2xl',
    '3xl' => 'max-w-3xl',
    '4xl' => 'max-w-4xl',
    '5xl' => 'max-w-5xl',
    '6xl' => 'max-w-6xl',
    '7xl' => 'max-w-7xl',
    default => 'max-w-lg',
};
@endphp

<div {{ $attributes->merge(['class' => 'fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4 overflow-y-auto']) }}>
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full {{ $maxWidthClass }} mx-auto my-auto max-h-[90vh] overflow-y-auto">
        {{ $slot }}
    </div>
</div>
