{{-- resources/views/livewire/admin/branches/form.blade.php --}}
<div class="space-y-4">
    <div class="flex items-center justify-between gap-2">
        <div>
            <h1 class="text-lg font-semibold text-slate-800">
                {{ $branchId ? __('Edit Branch') : __('Create Branch') }}
            </h1>
            <p class="text-sm text-slate-500">
                {{ __('Basic branch information.') }}
            </p>
        </div>
    </div>

    <form wire:submit.prevent="save" class="space-y-4">
        <div class="grid gap-4 lg:grid-cols-3">
            <div class="lg:col-span-2">
                @livewire('shared.dynamic-form', ['schema' => $schema, 'data' => $form], key('branch-form-' . ($branchId ?? 'new')))
            </div>

            <div class="space-y-3">
                <div class="space-y-1">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="form.is_active"
                               class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500">
                        <span>{{ __('Active') }}</span>
                    </label>
                    @error('form.is_active')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-1">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="form.is_main"
                               class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500">
                        <span>{{ __('Main branch') }}</span>
                    </label>
                    @error('form.is_main')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('admin.branches.index') }}"
               class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                {{ __('Cancel') }}
            </a>
            <button type="submit" class="erp-btn-primary">
                {{ $branchId ? __('Save changes') : __('Create branch') }}
            </button>
        </div>
    </form>
</div>
