<div class="space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-lg font-semibold text-slate-800 dark:text-slate-100">
                {{ $isEditing ? __('Edit Ticket') : __('Create Ticket') }}
            </h1>
        </div>
        <a href="{{ route('app.helpdesk.tickets.index') }}" class="erp-btn-secondary text-xs">
            {{ __('Back to list') }}
        </a>
    </div>

    <form wire:submit="save" class="space-y-4">
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/80 p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Subject -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('Subject') }} <span class="text-red-500">*</span>
                    </label>
                    <input type="text" wire:model="subject" class="erp-input w-full" required>
                    @error('subject') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <!-- Description -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('Description') }} <span class="text-red-500">*</span>
                    </label>
                    <textarea wire:model="description" rows="4" class="erp-input w-full" required></textarea>
                    @error('description') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <!-- Customer -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('Customer') }}
                    </label>
                    <select wire:model="customer_id" class="erp-input w-full">
                        <option value="">{{ __('Select customer') }}</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                        @endforeach
                    </select>
                    @error('customer_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <!-- Category -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('Category') }}
                    </label>
                    <select wire:model="category_id" class="erp-input w-full">
                        <option value="">{{ __('Select category') }}</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <!-- Priority -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('Priority') }}
                    </label>
                    <select wire:model="priority_id" class="erp-input w-full">
                        <option value="">{{ __('Select priority') }}</option>
                        @foreach($priorities as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                    @error('priority_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <!-- Status -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('Status') }}
                    </label>
                    <select wire:model="status" class="erp-input w-full">
                        <option value="new">{{ __('New') }}</option>
                        <option value="open">{{ __('Open') }}</option>
                        <option value="pending">{{ __('Pending') }}</option>
                        <option value="resolved">{{ __('Resolved') }}</option>
                        <option value="closed">{{ __('Closed') }}</option>
                    </select>
                    @error('status') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <!-- Assigned To -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('Assign To') }}
                    </label>
                    <select wire:model="assigned_to" class="erp-input w-full">
                        <option value="">{{ __('Unassigned') }}</option>
                        @foreach($agents as $agent)
                            <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                        @endforeach
                    </select>
                    @error('assigned_to') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <!-- Due Date -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('Due Date') }}
                    </label>
                    <input type="date" wire:model="due_date" class="erp-input w-full">
                    @error('due_date') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('app.helpdesk.tickets.index') }}" class="erp-btn-secondary">
                {{ __('Cancel') }}
            </a>
            <button type="submit" class="erp-btn-primary">
                {{ $isEditing ? __('Update Ticket') : __('Create Ticket') }}
            </button>
        </div>
    </form>
</div>
