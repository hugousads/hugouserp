{{-- resources/views/livewire/shared/search-input.blade.php --}}
<div>
    <input type="search" wire:model.live.debounce.500ms="query"
           placeholder="{{ __('Search...') }}"
           class="erp-input rounded-full">
</div>
