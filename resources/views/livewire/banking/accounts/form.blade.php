<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">
                {{ $isEditing ? __('Edit Bank Account') : __('Create Bank Account') }}
            </h1>
            <p class="text-sm text-slate-500">{{ __('Bank account information') }}</p>
        </div>
        <a href="{{ route('app.banking.accounts.index') }}" class="erp-btn erp-btn-secondary">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            {{ __('Back to List') }}
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200">
        <form wire:submit="save" class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label for="account_name" class="block text-sm font-medium text-slate-700 mb-2">
                        {{ __('Account Name') }} <span class="text-red-500">*</span>
                    </label>
                    <input type="text" wire:model="account_name" id="account_name" class="erp-input @error('account_name') border-red-500 @enderror" placeholder="{{ __('e.g., Main Business Account') }}">
                    @error('account_name') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="bank_name" class="block text-sm font-medium text-slate-700 mb-2">
                        {{ __('Bank Name') }} <span class="text-red-500">*</span>
                    </label>
                    <input type="text" wire:model="bank_name" id="bank_name" class="erp-input @error('bank_name') border-red-500 @enderror">
                    @error('bank_name') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="bank_branch" class="block text-sm font-medium text-slate-700 mb-2">
                        {{ __('Bank Branch') }}
                    </label>
                    <input type="text" wire:model="bank_branch" id="bank_branch" class="erp-input @error('bank_branch') border-red-500 @enderror">
                    @error('bank_branch') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="account_number" class="block text-sm font-medium text-slate-700 mb-2">
                        {{ __('Account Number') }} <span class="text-red-500">*</span>
                    </label>
                    <input type="text" wire:model="account_number" id="account_number" class="erp-input @error('account_number') border-red-500 @enderror">
                    @error('account_number') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="iban" class="block text-sm font-medium text-slate-700 mb-2">
                        {{ __('IBAN') }}
                    </label>
                    <input type="text" wire:model="iban" id="iban" class="erp-input @error('iban') border-red-500 @enderror">
                    @error('iban') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="swift_code" class="block text-sm font-medium text-slate-700 mb-2">
                        {{ __('SWIFT Code') }}
                    </label>
                    <input type="text" wire:model="swift_code" id="swift_code" class="erp-input @error('swift_code') border-red-500 @enderror">
                    @error('swift_code') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="account_type" class="block text-sm font-medium text-slate-700 mb-2">
                        {{ __('Account Type') }} <span class="text-red-500">*</span>
                    </label>
                    <select wire:model="account_type" id="account_type" class="erp-input @error('account_type') border-red-500 @enderror">
                        <option value="checking">{{ __('Checking') }}</option>
                        <option value="savings">{{ __('Savings') }}</option>
                        <option value="credit">{{ __('Credit') }}</option>
                    </select>
                    @error('account_type') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="currency" class="block text-sm font-medium text-slate-700 mb-2">
                        {{ __('Currency') }} <span class="text-red-500">*</span>
                    </label>
                    <select wire:model="currency" id="currency" class="erp-input @error('currency') border-red-500 @enderror">
                        @if(is_array($currencies))
                            @foreach($currencies as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        @endif
                    </select>
                    @error('currency') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="opening_date" class="block text-sm font-medium text-slate-700 mb-2">
                        {{ __('Opening Date') }} <span class="text-red-500">*</span>
                    </label>
                    <input type="date" wire:model="opening_date" id="opening_date" class="erp-input @error('opening_date') border-red-500 @enderror">
                    @error('opening_date') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="opening_balance" class="block text-sm font-medium text-slate-700 mb-2">
                        {{ __('Opening Balance') }} <span class="text-red-500">*</span>
                    </label>
                    <input type="number" step="0.01" wire:model="opening_balance" id="opening_balance" class="erp-input @error('opening_balance') border-red-500 @enderror">
                    @error('opening_balance') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                </div>

                <div class="md:col-span-2">
                    <label for="notes" class="block text-sm font-medium text-slate-700 mb-2">
                        {{ __('Notes') }}
                    </label>
                    <textarea wire:model="notes" id="notes" rows="3" class="erp-input @error('notes') border-red-500 @enderror"></textarea>
                    @error('notes') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200">
                <a href="{{ route('app.banking.accounts.index') }}" class="erp-btn erp-btn-secondary">{{ __('Cancel') }}</a>
                <button type="submit" class="erp-btn erp-btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove>{{ $isEditing ? __('Update Account') : __('Create Account') }}</span>
                    <span wire:loading>{{ __('Saving...') }}</span>
                </button>
            </div>
        </form>
    </div>
</div>
