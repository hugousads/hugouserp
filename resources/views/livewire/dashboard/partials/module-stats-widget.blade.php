{{-- Module Stats Widget (Generic template for module-specific widgets) --}}
@php
    $widgetKey = $widgetConfig['key'] ?? 'unknown';
    $widgetTitle = $widgetConfig['title_ar'] ?? $widgetConfig['title'] ?? 'Module Stats';
    $widgetIcon = $widgetConfig['icon'] ?? '📊';
    $moduleName = $widgetConfig['module'] ?? '';
@endphp
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            <span class="text-2xl">{{ $widgetIcon }}</span>
            <h3 class="text-lg font-semibold text-slate-800 dark:text-white">{{ __($widgetTitle) }}</h3>
        </div>
        @if($moduleName)
        <span class="px-2 py-1 bg-slate-100 dark:bg-slate-700 text-xs rounded-full text-slate-600 dark:text-slate-400">
            {{ ucfirst($moduleName) }}
        </span>
        @endif
    </div>
    
    <div class="flex flex-col items-center justify-center py-8 text-slate-400 dark:text-slate-500">
        <svg class="w-12 h-12 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
        </svg>
        <p class="text-sm font-medium mb-1">{{ __('Module Statistics') }}</p>
        <p class="text-xs text-center max-w-xs">
            {{ __('Statistics for this module will be displayed here when data is available.') }}
        </p>
    </div>
</div>
