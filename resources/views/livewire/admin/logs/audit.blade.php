<div class="space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-lg font-semibold text-slate-800">
                {{ __('Audit log') }}
            </h1>
            <p class="text-sm text-slate-500">
                {{ __('History of role and permission related changes.') }}
            </p>
        </div>
    </div>


    <div class="rounded-2xl border border-slate-200 bg-white/80 px-3 py-2 text-xs text-slate-700 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 items-end">
        <div>
            <label class="block text-[11px] font-medium text-slate-500 mb-0.5">
                {{ __('Actor') }}
            </label>
            <select wire:model.live.debounce.500ms="actorId"
                    class="rounded border border-slate-200 bg-white px-2 py-1 text-xs">
                <option value="">{{ __('All') }}</option>
                @foreach ($actors as $actor)
                    <option value="{{ $actor->id }}">{{ $actor->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-medium text-slate-500 mb-0.5">
                {{ __('From date') }}
            </label>
            <input type="date"
                   wire:model.live.debounce.500ms="dateFrom"
                   class="rounded border border-slate-200 bg-white px-2 py-1 text-xs">
        </div>
        <div>
            <label class="block text-[11px] font-medium text-slate-500 mb-0.5">
                {{ __('To date') }}
            </label>
            <input type="date"
                   wire:model.live.debounce.500ms="dateTo"
                   class="rounded border border-slate-200 bg-white px-2 py-1 text-xs">
        </div>
    </div>


        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600">
                        {{ __('Date') }}
                    </th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600">
                        {{ __('Actor') }}
                    </th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600">
                        {{ __('Target user') }}
                    </th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600">
                        {{ __('Action') }}
                    </th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600">
                        {{ __('Details') }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @forelse ($logs as $log)
                    <tr>
                        <td class="px-3 py-2 text-xs text-slate-600">
                            {{ $log->created_at?->format('Y-m-d H:i') }}
                        </td>
                        <td class="px-3 py-2 text-xs text-slate-700">
                            {{ $log->user?->name ?? '—' }}
                        </td>
                        <td class="px-3 py-2 text-xs text-slate-700">
                            {{ $log->targetUser?->name ?? '—' }}
                        </td>
                        <td class="px-3 py-2 text-xs text-slate-700">
                            {{ $log->action }}
                        </td>
                        <td class="px-3 py-2 text-xs text-slate-600">
                            @php
                                $meta = $log->meta ?? [];
                            @endphp
                            @if (!empty($meta['roles_before']) || !empty($meta['roles_after']))
                                <div class="space-y-1">
                                    @if (!empty($meta['roles_before']))
                                        <div>
                                            <span class="font-semibold">{{ __('Before') }}:</span>
                                            <span>{{ implode(', ', $meta['roles_before']) }}</span>
                                        </div>
                                    @endif
                                    @if (!empty($meta['roles_after']))
                                        <div>
                                            <span class="font-semibold">{{ __('After') }}:</span>
                                            <span>{{ implode(', ', $meta['roles_after']) }}</span>
                                        </div>
                                    @endif
                                </div>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3 py-6 text-center text-xs text-slate-400">
                            {{ __('No audit log entries yet.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>

        <div class="border-t border-slate-100 bg-slate-50 px-3 py-2">
            {{ $logs->links() }}
        </div>
    </div>
</div>
