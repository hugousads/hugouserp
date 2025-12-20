<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">{{ __('Documents') }}</h1>
            <p class="text-sm text-slate-500">{{ __('Manage documents and files') }}</p>
        </div>
        <div class="flex items-center gap-2">
            @can('documents.create')
            <a href="{{ route('app.documents.create') }}" class="erp-btn erp-btn-primary">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('Upload Document') }}
            </a>
            @endcan
        </div>
    </div>

    {{-- Statistics --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-4 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm">{{ __('Total Documents') }}</p>
                    <p class="text-2xl font-bold">{{ number_format($stats['total_documents']) }}</p>
                </div>
                <svg class="w-8 h-8 text-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
            </div>
        </div>
        <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-xl p-4 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-emerald-100 text-sm">{{ __('Total Size') }}</p>
                    <p class="text-2xl font-bold">{{ $stats['total_size_formatted'] }}</p>
                </div>
                <svg class="w-8 h-8 text-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/></svg>
            </div>
        </div>
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-4 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-sm">{{ __('My Documents') }}</p>
                    <p class="text-2xl font-bold">{{ $documents->where('uploaded_by', auth()->id())->count() }}</p>
                </div>
                <svg class="w-8 h-8 text-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ __('Search') }}</label>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search documents...') }}" class="erp-input w-full">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ __('Category') }}</label>
                <select wire:model.live="category" class="erp-input w-full">
                    <option value="">{{ __('All Categories') }}</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}">{{ $cat }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ __('Folder') }}</label>
                <select wire:model.live="folder" class="erp-input w-full">
                    <option value="">{{ __('All Folders') }}</option>
                    @foreach($folders as $f)
                        <option value="{{ $f }}">{{ $f }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ __('Tag') }}</label>
                <select wire:model.live="tag" class="erp-input w-full">
                    <option value="">{{ __('All Tags') }}</option>
                    @foreach($tags as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Documents Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
        @forelse($documents as $doc)
            <div class="bg-white rounded-xl shadow-sm p-4 hover:shadow-md transition-shadow">
                <a href="{{ route('app.documents.show', $doc->id) }}" class="block">
                    <div class="flex items-center justify-center h-32 bg-slate-100 rounded-lg mb-3">
                        @if(str_contains($doc->mime_type, 'image'))
                            @php($previewUrl = route('app.documents.download', ['document' => $doc->id, 'inline' => true]))
                            <img src="{{ $previewUrl }}" alt="{{ $doc->title }}" class="h-full w-full object-cover rounded-lg">
                        @else
                            <svg class="w-16 h-16 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        @endif
                    </div>
                    <h3 class="font-semibold text-slate-900 truncate mb-1">{{ $doc->title }}</h3>
                    <p class="text-xs text-slate-500 mb-2">{{ $doc->getFileSizeFormatted() }} • {{ $doc->file_type }}</p>
                    <div class="flex items-center gap-2 mb-2">
                        @foreach($doc->tags as $tag)
                            <span class="px-2 py-0.5 text-xs rounded" style="background-color: {{ $tag->color }}20; color: {{ $tag->color }}">
                                {{ $tag->name }}
                            </span>
                        @endforeach
                    </div>
                    <p class="text-xs text-slate-400">{{ $doc->uploader->name }} • {{ $doc->created_at->diffForHumans() }}</p>
                </a>
                <div class="flex items-center gap-2 mt-3 pt-3 border-t">
                    <a href="{{ route('app.documents.show', $doc->id) }}" class="text-xs text-blue-600 hover:text-blue-900">{{ __('View') }}</a>
                    @can('documents.edit')
                        @if($doc->uploaded_by === auth()->id())
                            <a href="{{ route('app.documents.edit', $doc->id) }}" class="text-xs text-emerald-600 hover:text-emerald-900">{{ __('Edit') }}</a>
                        @endif
                    @endcan
                    @can('documents.delete')
                        @if($doc->uploaded_by === auth()->id())
                            <button wire:click="delete({{ $doc->id }})" wire:confirm="{{ __('Are you sure?') }}" class="text-xs text-red-600 hover:text-red-900">{{ __('Delete') }}</button>
                        @endif
                    @endcan
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-12">
                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                <p class="mt-2 text-slate-500">{{ __('No documents found') }}</p>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div class="mt-6">
        {{ $documents->links() }}
    </div>
</div>
