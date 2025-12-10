{{-- resources/views/livewire/expenses/categories/index.blade.php --}}
@section('page-header')
    <h1 class="text-xl font-bold text-slate-800 dark:text-white flex items-center gap-2">
        <span class="text-2xl">📁</span>
        {{ __('Expense Categories') }}
    </h1>
    <p class="text-sm text-slate-500">{{ __('Manage expense categories for organizing expenses') }}</p>
@endsection

@section('page-actions')
    <button wire:click="openModal" class="erp-btn-primary">
        <svg class="w-5 h-5 ltr:mr-1 rtl:ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        {{ __('Add Category') }}
    </button>
@endsection

<div class="space-y-4">
    @if(session('success'))
        <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-700 flex items-center gap-2">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 flex items-center gap-2">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            {{ session('error') }}
        </div>
    @endif

    <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
        <div class="relative w-full sm:w-80">
            <input type="text" 
                   wire:model.live.debounce.300ms="search" 
                   placeholder="{{ __('Search categories...') }}"
                   class="erp-input ltr:pl-10 rtl:pr-10">
            <svg class="absolute ltr:left-3 rtl:right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
        <table class="erp-table">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Arabic Name') }}</th>
                    <th>{{ __('Description') }}</th>
                    <th class="text-center">{{ __('Expenses') }}</th>
                    <th class="text-center">{{ __('Status') }}</th>
                    <th class="text-center">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($categories as $category)
                    <tr wire:key="cat-{{ $category->id }}">
                        <td class="font-medium">{{ $category->name }}</td>
                        <td dir="rtl" class="text-right">{{ $category->name_ar ?: '-' }}</td>
                        <td class="max-w-xs truncate">{{ $category->description ?: '-' }}</td>
                        <td class="text-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800">
                                {{ $category->expenses_count }}
                            </span>
                        </td>
                        <td class="text-center">
                            <button wire:click="toggleActive({{ $category->id }})" 
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $category->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">
                                {{ $category->is_active ? __('Active') : __('Inactive') }}
                            </button>
                        </td>
                        <td class="text-center">
                            <div class="flex items-center justify-center gap-1">
                                <button wire:click="edit({{ $category->id }})" 
                                        class="erp-btn-icon" 
                                        title="{{ __('Edit') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </button>
                                <button wire:click="delete({{ $category->id }})" 
                                        wire:confirm="{{ __('Are you sure you want to delete this category?') }}"
                                        class="erp-btn-icon text-red-500 hover:text-red-700 hover:bg-red-50"
                                        title="{{ __('Delete') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-8 text-slate-500">
                            {{ __('No categories found') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $categories->links() }}
    </div>
</div>

<!-- Modal -->
@if($showModal)
<div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-slate-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="closeModal"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white dark:bg-slate-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white dark:bg-slate-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-slate-900 dark:text-white">
                        {{ $editingId ? __('Edit Category') : __('Add Category') }}
                    </h3>
                    <button wire:click="closeModal" class="text-slate-400 hover:text-slate-500">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                
                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label class="erp-label">{{ __('Name') }} <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="name" class="erp-input @error('name') border-red-500 @enderror">
                        @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="erp-label">{{ __('Arabic Name') }}</label>
                        <input type="text" wire:model="nameAr" class="erp-input" dir="rtl">
                        @error('nameAr') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="erp-label">{{ __('Description') }}</label>
                        <textarea wire:model="description" rows="3" class="erp-input"></textarea>
                        @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" wire:model="isActive" id="isActive" class="rounded border-slate-300 text-emerald-600">
                        <label for="isActive" class="text-sm">{{ __('Active') }}</label>
                    </div>

                    <div class="flex justify-end gap-3 pt-4">
                        <button type="button" wire:click="closeModal" class="erp-btn erp-btn-secondary">
                            {{ __('Cancel') }}
                        </button>
                        <button type="submit" class="erp-btn erp-btn-primary">
                            {{ $editingId ? __('Update') : __('Save') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endif
