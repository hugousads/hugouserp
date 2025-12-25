<div class="p-6 max-w-4xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <a href="{{ route('admin.activity-log') }}" class="inline-flex items-center text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300 mb-2">
                <svg class="w-4 h-4 me-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                {{ __('Back to Activity Log') }}
            </a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Activity Details') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Detailed view of system activity') }}</p>
        </div>
    </div>

    <!-- Activity Info Card -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Activity Information') }}</h2>
        </div>
        <div class="p-6">
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Date & Time') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                        {{ $activity->created_at->format('M d, Y \a\t H:i:s') }}
                        <span class="text-gray-500 dark:text-gray-400">({{ $activity->created_at->diffForHumans() }})</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('User') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                        @if($activity->causer)
                            <div class="flex items-center">
                                <div class="h-8 w-8 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center">
                                    <span class="text-xs font-medium text-indigo-700 dark:text-indigo-300">
                                        {{ strtoupper(substr($activity->causer->name ?? 'S', 0, 2)) }}
                                    </span>
                                </div>
                                <div class="ms-3">
                                    <div class="font-medium">{{ $activity->causer->name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $activity->causer->email }}</div>
                                </div>
                            </div>
                        @else
                            <span class="text-gray-500 dark:text-gray-400">{{ __('System') }}</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Event Type') }}</dt>
                    <dd class="mt-1">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            @if($activity->event === 'created') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300
                            @elseif($activity->event === 'updated') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300
                            @elseif($activity->event === 'deleted') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300
                            @elseif($activity->event === 'restored') bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300
                            @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                            @endif">
                            {{ __(ucfirst($activity->event ?? 'Action')) }}
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Log Type') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ ucfirst($activity->log_name ?? 'default') }}</dd>
                </div>
                <div class="md:col-span-2">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Description') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $activity->description }}</dd>
                </div>
                @if($activity->subject)
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Affected Record') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                        <span class="font-medium">{{ class_basename($activity->subject_type) }}</span>
                        <span class="text-gray-500 dark:text-gray-400">#{{ $activity->subject_id }}</span>
                    </dd>
                </div>
                @endif
            </dl>
        </div>
    </div>

    <!-- Changes Card -->
    @if(!empty($formattedProperties['changes']))
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Changes Made') }}</h2>
        </div>
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Field') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Previous Value') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('New Value') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($formattedProperties['changes'] as $change)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">{{ $change['field'] }}</td>
                            <td class="px-4 py-3 text-sm text-red-600 dark:text-red-400">
                                <div class="bg-red-50 dark:bg-red-900/20 rounded px-2 py-1 inline-block">
                                    {{ $change['old'] }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-green-600 dark:text-green-400">
                                <div class="bg-green-50 dark:bg-green-900/20 rounded px-2 py-1 inline-block">
                                    {{ $change['new'] }}
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <!-- New Values Card (for created events) -->
    @if(!empty($formattedProperties['attributes']))
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Created Record Details') }}</h2>
        </div>
        <div class="p-6">
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($formattedProperties['attributes'] as $attr)
                <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $attr['field'] }}</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white break-words">{{ $attr['value'] }}</dd>
                </div>
                @endforeach
            </dl>
        </div>
    </div>
    @endif

    <!-- Other Properties Card -->
    @if(!empty($formattedProperties['other']))
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Additional Information') }}</h2>
        </div>
        <div class="p-6">
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($formattedProperties['other'] as $prop)
                <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $prop['field'] }}</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white break-words">{{ $prop['value'] }}</dd>
                </div>
                @endforeach
            </dl>
        </div>
    </div>
    @endif

    <!-- No Data Card -->
    @if(empty($formattedProperties['changes']) && empty($formattedProperties['attributes']) && empty($formattedProperties['other']))
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <div class="p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('No additional details recorded for this activity') }}</p>
        </div>
    </div>
    @endif
</div>
