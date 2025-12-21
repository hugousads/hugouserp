<div class="max-w-6xl mx-auto py-6 space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">{{ __('Reports Hub') }}</h1>
            <p class="text-sm text-gray-600 mt-1">
                {{ __('Browse and run available report templates for POS, Inventory, Store and more.') }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3 text-sm">
            <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-3 py-1 text-blue-700">
                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                {{ __('Scheduled:') }} {{ $scheduledCount }}
            </span>
            <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-3 py-1 text-red-700">
                <span class="w-2 h-2 rounded-full bg-red-500"></span>
                {{ __('Failed (last status):') }} {{ $failedCount }}
            </span>
        </div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-200 pb-4">
        <div class="flex flex-wrap gap-2 text-xs">
            @php
                $modules = [
                    'all'      => __('All modules'),
                    'pos'      => __('POS'),
                    'inventory'=> __('Inventory'),
                    'store'    => __('Store'),
                    'general'  => __('General'),
                ];
            @endphp

            @foreach ($modules as $key => $label)
                <a href="{{ request()->fullUrlWithQuery(['module' => $key]) }}"
                   wire:navigate
                   class="inline-flex items-center rounded-full px-3 py-1 border text-xs font-medium transition
                        {{ $module === $key ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <div class="flex items-center gap-3">
            <div class="relative">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Search reports...') }}"
                    class="block w-52 rounded-md border-gray-300 pl-8 pr-3 py-1.5 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                <span class="absolute inset-y-0 left-2 flex items-center text-gray-400">
                    🔍
                </span>
            </div>
        </div>
    </div>

    <div class="space-y-8 mt-4">
        @forelse ($templatesByModule as $moduleKey => $templates)
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-800">
                        @switch($moduleKey)
                            @case('pos')
                                {{ __('POS Reports') }}
                                @break
                            @case('inventory')
                                {{ __('Inventory Reports') }}
                                @break
                            @case('store')
                                {{ __('Store Reports') }}
                                @break
                            @default
                                {{ ucfirst($moduleKey) }} {{ __('Reports') }}
                        @endswitch
                    </h2>
                    <span class="text-xs text-gray-500">
                        {{ trans_choice(':count template|:count templates', $templates->count(), ['count' => $templates->count()]) }}
                    </span>
                </div>

                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($templates as $template)
                        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm flex flex-col justify-between">
                            <div class="space-y-1">
                                <div class="flex items-center justify-between gap-2">
                                    <h3 class="text-base font-semibold text-gray-900">
                                        {{ $template->name }}
                                    </h3>
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-600">
                                        {{ $template->key }}
                                    </span>
                                </div>
                                @if ($template->description)
                                    <p class="text-xs text-gray-600 line-clamp-3">
                                        {{ $template->description }}
                                    </p>
                                @endif
                            </div>

                            <div class="mt-3 flex items-center justify-between text-xs text-gray-500">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-medium text-emerald-700">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        {{ strtoupper($template->output_type ?? 'web') }}
                                    </span>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-gray-50 px-2 py-0.5 text-[10px] font-medium text-gray-600">
                                        {{ $template->module ?? 'general' }}
                                    </span>
                                </div>

                                <div class="flex gap-2">
                                    @if ($template->route_name)
                                        <a href="{{ route($template->route_name, $template->default_filters ?? []) }}"
                                           class="inline-flex items-center rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                                            {{ __('Open') }}
                                        </a>
                                    @endif

                                    <a href="{{ route('admin.reports.scheduled') }}"
                                       class="inline-flex items-center rounded-md border border-indigo-500 bg-indigo-50 px-2.5 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100">
                                        {{ __('Schedule') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="text-gray-500 text-sm">{{ __('No report templates defined yet.') }}</p>
        @endforelse
    </div>
</div>
