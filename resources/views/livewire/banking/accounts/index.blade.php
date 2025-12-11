<div class="space-y-6">
    <div wire:loading.delay class="fixed inset-0 bg-slate-900/20 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-xl p-6 flex items-center gap-3">
            <svg class="animate-spin h-6 w-6 text-emerald-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-slate-700 font-medium">{{ __('Loading...') }}</span>
        </div>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">{{ __('Bank Accounts') }}</h1>
            <p class="text-sm text-slate-500">{{ __('Manage bank accounts and balances') }}</p>
        </div>
        <div class="flex items-center gap-2">
            @can('banking.create')
            <a href="{{ route('app.banking.accounts.create') }}" class="erp-btn erp-btn-primary">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('New Account') }}
            </a>
            @endcan
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-4 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm">{{ __('Total Accounts') }}</p>
                    <p class="text-2xl font-bold">{{ number_format($statistics['total_accounts']) }}</p>
                </div>
                <div class="bg-white/20 rounded-lg p-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                </div>
            </div>
        </div>
        <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-xl p-4 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-emerald-100 text-sm">{{ __('Active Accounts') }}</p>
                    <p class="text-2xl font-bold">{{ number_format($statistics['active_accounts']) }}</p>
                </div>
                <div class="bg-white/20 rounded-lg p-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>
        <div class="bg-gradient-to-br from-amber-500 to-amber-600 rounded-xl p-4 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-amber-100 text-sm">{{ __('Total Balance') }}</p>
                    <p class="text-2xl font-bold">{{ number_format($statistics['total_balance'], 2) }}</p>
                </div>
                <div class="bg-white/20 rounded-lg p-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-4 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-sm">{{ __('Currencies') }}</p>
                    <p class="text-2xl font-bold">{{ number_format($statistics['currencies']) }}</p>
                </div>
                <div class="bg-white/20 rounded-lg p-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>
    </div>

    <div class="erp-card p-4">
        <div class="flex flex-col lg:flex-row gap-4 mb-6">
            <div class="flex-1 relative">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search by account name, number or bank...') }}" class="erp-input pr-10">
            </div>
            <div class="relative">
                <select wire:model.live="status" class="erp-input w-full lg:w-40">
                    <option value="">{{ __('All Status') }}</option>
                    <option value="active">{{ __('Active') }}</option>
                    <option value="inactive">{{ __('Inactive') }}</option>
                    <option value="closed">{{ __('Closed') }}</option>
                </select>
            </div>
            @if($currencies->count() > 1)
            <div class="relative">
                <select wire:model.live="currency" class="erp-input w-full lg:w-32">
                    <option value="">{{ __('All Currencies') }}</option>
                    @foreach($currencies as $curr)
                        <option value="{{ $curr }}">{{ $curr }}</option>
                    @endforeach
                </select>
            </div>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="erp-table">
                <thead>
                    <tr>
                        <th wire:click="sortBy('account_name')" class="cursor-pointer hover:bg-slate-100">
                            <div class="flex items-center gap-2">{{ __('Account Name') }}</div>
                        </th>
                        <th>{{ __('Bank Name') }}</th>
                        <th>{{ __('Account Number') }}</th>
                        <th>{{ __('Account Type') }}</th>
                        <th>{{ __('Current Balance') }}</th>
                        <th>{{ __('Currency') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-center">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($accounts as $account)
                        <tr wire:key="account-{{ $account->id }}">
                            <td class="font-medium text-slate-800">{{ $account->account_name }}</td>
                            <td>{{ $account->bank_name }}</td>
                            <td class="font-mono text-sm">{{ $account->account_number }}</td>
                            <td>
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-slate-100 text-slate-700">
                                    {{ ucfirst($account->account_type) }}
                                </span>
                            </td>
                            <td class="font-medium text-emerald-600">{{ number_format($account->current_balance, 2) }}</td>
                            <td>{{ $account->currency }}</td>
                            <td>
                                @if($account->status === 'active')
                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-emerald-100 text-emerald-700">{{ __('Active') }}</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-slate-100 text-slate-700">{{ ucfirst($account->status) }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex items-center justify-center gap-2">
                                    @can('banking.edit')
                                    <a href="{{ route('app.banking.accounts.edit', $account) }}" class="text-blue-600 hover:text-blue-800">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-12">
                                <div class="text-slate-400">
                                    <svg class="w-16 h-16 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                    <p class="text-lg font-medium">{{ __('No bank accounts found') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($accounts->hasPages())
            <div class="mt-4">{{ $accounts->links() }}</div>
        @endif
    </div>
</div>
