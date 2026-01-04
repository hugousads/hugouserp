@props([
    'show' => false,
    'title' => '',
    'maxWidth' => 'lg',
    'closeable' => true,
])

@php
$maxWidthClass = match($maxWidth) {
    'sm' => 'sm:max-w-sm',
    'md' => 'sm:max-w-md',
    'xl' => 'sm:max-w-xl',
    '2xl' => 'sm:max-w-2xl',
    'full' => 'sm:max-w-full sm:mx-4',
    default => 'sm:max-w-lg',
};
@endphp

<div
    x-data="{
        show: @js($show),
        startY: 0,
        currentY: 0,
        isDragging: false,
        
        open() {
            this.show = true;
            document.body.style.overflow = 'hidden';
        },
        
        close() {
            @if($closeable)
                this.show = false;
                document.body.style.overflow = '';
                $dispatch('close-bottom-sheet');
            @endif
        },
        
        handleTouchStart(e) {
            if (!e.target.closest('.bottom-sheet-handle')) return;
            this.isDragging = true;
            this.startY = e.touches[0].clientY;
        },
        
        handleTouchMove(e) {
            if (!this.isDragging) return;
            this.currentY = e.touches[0].clientY;
            const diff = this.currentY - this.startY;
            if (diff > 0) {
                this.$refs.sheet.style.transform = `translateY(${diff}px)`;
            }
        },
        
        handleTouchEnd() {
            if (!this.isDragging) return;
            this.isDragging = false;
            const diff = this.currentY - this.startY;
            if (diff > 100) {
                this.close();
            }
            this.$refs.sheet.style.transform = '';
        }
    }"
    x-show="show"
    x-on:open-bottom-sheet.window="open()"
    x-on:close-bottom-sheet.window="close()"
    x-on:keydown.escape.window="close()"
    x-cloak
    class="fixed inset-0 z-50 overflow-hidden"
    aria-modal="true"
    role="dialog"
>
    {{-- Backdrop --}}
    <div
        x-show="show"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="close()"
        class="fixed inset-0 bg-black/50 backdrop-blur-sm"
    ></div>

    {{-- Bottom Sheet Container --}}
    <div class="fixed inset-x-0 bottom-0 flex flex-col items-center">
        <div
            x-ref="sheet"
            x-show="show"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-full"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-full"
            @touchstart="handleTouchStart($event)"
            @touchmove.passive="handleTouchMove($event)"
            @touchend="handleTouchEnd()"
            class="w-full {{ $maxWidthClass }} bg-white dark:bg-slate-800 rounded-t-2xl shadow-2xl max-h-[90vh] overflow-hidden flex flex-col"
        >
            {{-- Handle Bar --}}
            <div class="bottom-sheet-handle flex-shrink-0 p-2 cursor-grab active:cursor-grabbing">
                <div class="w-10 h-1 bg-slate-300 dark:bg-slate-600 rounded-full mx-auto"></div>
            </div>

            {{-- Header --}}
            @if($title || $closeable)
            <div class="flex items-center justify-between px-4 pb-3 border-b border-slate-200 dark:border-slate-700">
                @if($title)
                <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                    {{ $title }}
                </h3>
                @endif
                
                @if($closeable)
                <button 
                    @click="close()" 
                    type="button"
                    class="p-2 text-slate-400 hover:text-slate-500 dark:hover:text-slate-300 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition"
                    aria-label="{{ __('Close') }}"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
                @endif
            </div>
            @endif

            {{-- Content --}}
            <div class="flex-1 overflow-y-auto overscroll-contain p-4">
                {{ $slot }}
            </div>

            {{-- Footer Actions --}}
            @if(isset($footer))
            <div class="flex-shrink-0 px-4 py-3 bg-slate-50 dark:bg-slate-900/50 border-t border-slate-200 dark:border-slate-700 safe-area-bottom">
                {{ $footer }}
            </div>
            @endif
        </div>
    </div>
</div>

<style>
    .safe-area-bottom {
        padding-bottom: max(0.75rem, env(safe-area-inset-bottom));
    }
</style>
