<div class="space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
            <h1 class="text-lg font-semibold text-slate-800 dark:text-slate-100">
                {{ __('Support Tickets') }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ __('Manage customer support tickets.') }}
            </p>
        </div>

        <div class="flex flex-col sm:flex-row items-center gap-2">
            <div class="w-full sm:w-56">
                <input type="search"
                       wire:model.live.debounce.500ms="search"
                       placeholder="{{ __('Search tickets...') }}"
                       class="erp-input rounded-full">
            </div>

            <div class="flex items-center gap-2">
                <select wire:model.live="status" class="erp-input text-xs w-32">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="new">{{ __('New') }}</option>
                    <option value="open">{{ __('Open') }}</option>
                    <option value="pending">{{ __('Pending') }}</option>
                    <option value="resolved">{{ __('Resolved') }}</option>
                    <option value="closed">{{ __('Closed') }}</option>
                </select>

                <a href="{{ route('app.helpdesk.tickets.create') }}"
                   class="erp-btn-primary text-xs px-3 py-2">
                    {{ __('New Ticket') }}
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/80 p-3">
            <div class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('New') }}</div>
            <div class="mt-1 text-xl font-semibold text-blue-600 dark:text-blue-400">{{ $stats['new'] }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/80 p-3">
            <div class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Open') }}</div>
            <div class="mt-1 text-xl font-semibold text-green-600 dark:text-green-400">{{ $stats['open'] }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/80 p-3">
            <div class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Pending') }}</div>
            <div class="mt-1 text-xl font-semibold text-yellow-600 dark:text-yellow-400">{{ $stats['pending'] }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/80 p-3">
            <div class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Overdue') }}</div>
            <div class="mt-1 text-xl font-semibold text-red-600 dark:text-red-400">{{ $stats['overdue'] }}</div>
        </div>
    </div>

    <!-- Tickets Table -->
    <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/80 shadow-sm shadow-emerald-500/10">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800/80">
                <tr>
                    <th class="px-3 py-2 text-start text-xs font-semibold text-slate-500 dark:text-slate-300">
                        {{ __('Ticket #') }}
                    </th>
                    <th class="px-3 py-2 text-start text-xs font-semibold text-slate-500 dark:text-slate-300">
                        {{ __('Subject') }}
                    </th>
                    <th class="px-3 py-2 text-start text-xs font-semibold text-slate-500 dark:text-slate-300">
                        {{ __('Customer') }}
                    </th>
                    <th class="px-3 py-2 text-start text-xs font-semibold text-slate-500 dark:text-slate-300">
                        {{ __('Status') }}
                    </th>
                    <th class="px-3 py-2 text-start text-xs font-semibold text-slate-500 dark:text-slate-300">
                        {{ __('Priority') }}
                    </th>
                    <th class="px-3 py-2 text-start text-xs font-semibold text-slate-500 dark:text-slate-300">
                        {{ __('Assigned To') }}
                    </th>
                    <th class="px-3 py-2 text-start text-xs font-semibold text-slate-500 dark:text-slate-300">
                        {{ __('Created') }}
                    </th>
                    <th class="px-3 py-2 text-center text-xs font-semibold text-slate-500 dark:text-slate-300">
                        {{ __('Actions') }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                @forelse($tickets as $ticket)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-3 py-2 text-slate-700 dark:text-slate-200 font-medium">
                            {{ $ticket->ticket_number }}
                        </td>
                        <td class="px-3 py-2">
                            <div class="text-slate-700 dark:text-slate-200">{{ $ticket->subject }}</div>
                            @if($ticket->category)
                                <div class="text-xs text-slate-500 dark:text-slate-400">{{ $ticket->category->name }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-slate-600 dark:text-slate-300">
                            {{ $ticket->customer?->name ?? '-' }}
                        </td>
                        <td class="px-3 py-2">
                            @php
                                $statusColors = [
                                    'new' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                    'open' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                    'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                    'resolved' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
                                    'closed' => 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300',
                                ];
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $statusColors[$ticket->status] ?? '' }}">
                                {{ ucfirst($ticket->status) }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-slate-600 dark:text-slate-300">
                            {{ $ticket->priority?->name ?? '-' }}
                        </td>
                        <td class="px-3 py-2 text-slate-600 dark:text-slate-300">
                            {{ $ticket->assignedAgent?->name ?? __('Unassigned') }}
                        </td>
                        <td class="px-3 py-2 text-slate-600 dark:text-slate-300 text-xs">
                            {{ $ticket->created_at->format('Y-m-d H:i') }}
                        </td>
                        <td class="px-3 py-2 text-center">
                            <a href="{{ route('app.helpdesk.tickets.show', $ticket->id) }}"
                               class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                {{ __('View') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-3 py-6 text-center text-slate-500 dark:text-slate-400">
                            {{ __('No tickets found.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $tickets->links() }}
    </div>
</div>
